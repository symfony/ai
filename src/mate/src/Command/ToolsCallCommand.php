<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Command;

use HelgeSverre\Toon\Toon;
use Symfony\AI\Mate\Command\Trait\EnsuresToonFormatAvailabilityTrait;
use Symfony\AI\Mate\Discovery\CapabilityRegistry;
use Symfony\AI\Mate\Encoding\ResponseEncoder;
use Symfony\AI\Mate\Exception\InvalidArgumentException;
use Symfony\AI\Mate\Invocation\ToolInvoker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Execute a tool with its parameters passed as CLI options.
 *
 * Parameters are given as ergonomic long options (`--limit=1 --level=error`); for complex
 * or array-valued inputs, a full JSON object can be passed via `--json`. Because tool
 * parameters are not known ahead of time, validation errors for unknown options are
 * ignored and the raw tokens are parsed directly.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
#[AsCommand('tools:call', 'Execute a tool with its parameters')]
class ToolsCallCommand extends Command
{
    use EnsuresToonFormatAvailabilityTrait;

    /**
     * Options consumed by the command itself, never treated as tool parameters.
     */
    private const RESERVED_VALUE_OPTIONS = ['format', 'json'];

    /**
     * Global console flags that carry no tool parameter meaning.
     */
    private const GLOBAL_FLAGS = ['help', 'silent', 'quiet', 'verbose', 'version', 'ansi', 'no-ansi', 'no-interaction'];

    public function __construct(
        private CapabilityRegistry $registry,
        private ToolInvoker $invoker,
    ) {
        parent::__construct(self::getDefaultName());
    }

    public static function getDefaultName(): string
    {
        return 'tools:call';
    }

    public static function getDefaultDescription(): string
    {
        return 'Execute a tool with its parameters';
    }

    protected function configure(): void
    {
        $script = $_SERVER['PHP_SELF'] ?? 'vendor/bin/mate';

        // Tool parameters arrive as arbitrary long options that are not part of this
        // command's definition, so validation of unknown options must be disabled.
        $this->ignoreValidationErrors();

        $this
            // Optional so console validation does not reject `tools:call --limit=1 <tool>`; the raw
            // token parser resolves the name whatever its position, and execute() reports a miss.
            ->addArgument('tool-name', InputArgument::OPTIONAL, 'Name of the tool to execute')
            ->addOption('json', null, InputOption::VALUE_REQUIRED, 'Tool parameters as a JSON object (merged under any --<param> options)')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format (json, pretty, toon)', 'pretty')
            ->setHelp(
                <<<HELP
The <info>%command.name%</info> command executes a tool, passing each of its parameters as a long option.

<info>Usage Examples:</info>

  <comment># Execute a tool without parameters</comment>
  %command.full_name% server-info

  <comment># Pass parameters as options (values are coerced to the parameter types)</comment>
  %command.full_name% symfony-profiler-list --limit=1
  %command.full_name% monolog-search --term=error --level=error

  <comment># Boolean flags may be passed without a value</comment>
  %command.full_name% monolog-search --term="^GET" --regex

  <comment># Pass complex/array parameters as a JSON object</comment>
  %command.full_name% some-tool --json='{"tags": ["a", "b"]}'

  <comment># --json is also the only way to pass a parameter whose name is taken by this
  # command or by a global console flag: format, json, help, silent, quiet, verbose,
  # version, ansi, no-ansi, no-interaction</comment>
  %command.full_name% some-tool --json='{"format": "csv"}'

  <comment># JSON output format for scripting</comment>
  %command.full_name% server-info --format=json

  <comment># For a list of available tools, use:</comment>
  {$script} tools:list

  <comment># For a tool's parameters and schema, use:</comment>
  {$script} tools:inspect <tool-name>
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $verbose = $output->isVerbose();

        [$toolName, $format, $jsonOption, $dynamicParams] = $this->resolveInput($input);

        if (null === $toolName || '' === $toolName) {
            $io->error('Not enough arguments (missing: "tool-name").');

            return Command::INVALID;
        }

        if (!$this->ensureFormatSupported($io, $format, ['pretty', 'json', 'toon'])) {
            return Command::FAILURE;
        }

        $params = [];
        if (null !== $jsonOption) {
            $decoded = json_decode($jsonOption, true);
            if (\JSON_ERROR_NONE !== json_last_error()) {
                $io->error(\sprintf('Invalid JSON in --json: %s', json_last_error_msg()));

                return Command::FAILURE;
            }

            if (!\is_array($decoded)) {
                $io->error('The --json value must be a JSON object.');

                return Command::FAILURE;
            }

            $params = $decoded;
        }

        // Explicit --<param> options take precedence over the --json bag.
        $params = array_merge($params, $dynamicParams);

        $tool = $this->registry->findTool($toolName);
        if (null === $tool) {
            $io->error(\sprintf('Tool "%s" not found', $toolName));
            $io->note(\sprintf('Use "%s tools:list" to see all available tools', $_SERVER['PHP_SELF'] ?? 'vendor/bin/mate'));

            return Command::FAILURE;
        }

        if ('pretty' === $format) {
            $io->title(\sprintf('Executing Tool: %s', $toolName));
            if (null !== $tool->description) {
                $io->text($tool->description);
            }
            $io->newLine();
        }

        try {
            $result = $this->invoker->invoke($tool, $params);
        } catch (InvalidArgumentException $e) {
            // A wrong or missing parameter name is a guess the caller can correct right away;
            // point at the command that shows the full schema instead of leaving it a guess.
            $io->error(\sprintf('Error: %s', $e->getMessage()));
            $io->note(\sprintf('Use "%s tools:inspect %s" to see this tool\'s parameters.', $_SERVER['PHP_SELF'] ?? 'vendor/bin/mate', $toolName));
            if ($verbose) {
                $io->text($e->getTraceAsString());
            }

            return Command::FAILURE;
        } catch (\Throwable $e) {
            $io->error(\sprintf('Error: %s', $e->getMessage()));
            if ($verbose) {
                $io->text($e->getTraceAsString());
            }

            return Command::FAILURE;
        }

        if (\is_string($result)) {
            $result = ResponseEncoder::tryDecode($result);
        }

        if ('json' === $format) {
            $output->writeln(json_encode($result, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
        } elseif ('toon' === $format) {
            $output->writeln(Toon::encode($result));
        } else {
            $io->section('Result');
            $this->renderPretty($result, $io);
        }

        return Command::SUCCESS;
    }

    /**
     * Resolves the tool name, output format, raw JSON option and dynamic parameters.
     *
     * ArgvInput (real CLI usage) is parsed from raw tokens so arbitrary `--<param>`
     * options are supported. Other input types (used in tests) rely on the declared
     * options and the `--json` escape hatch only.
     *
     * @return array{0: string|null, 1: string, 2: string|null, 3: array<string, string|bool|list<string>>}
     */
    private function resolveInput(InputInterface $input): array
    {
        if ($input instanceof ArgvInput) {
            return $this->parseRawTokens($this->rawTokensAfterCommand($input));
        }

        $toolName = $input->getArgument('tool-name');
        $format = $input->getOption('format');
        $json = $input->getOption('json');

        return [
            \is_string($toolName) ? $toolName : null,
            \is_string($format) ? $format : 'pretty',
            \is_string($json) ? $json : null,
            [],
        ];
    }

    /**
     * Returns the raw command-line tokens that follow the command name (i.e. the tool
     * name and its dynamic `--<param>` options).
     *
     * This reproduces `ArgvInput::getRawTokens(true)` (added in symfony/console 7.1) so
     * the same "keep everything after the first argument" behavior works on the supported
     * 5.4/6.4 versions too. The `tokens` property is private but has existed on ArgvInput
     * across all supported versions, and reflection can read it without setAccessible() on
     * PHP 8.1+.
     *
     * @return list<string>
     */
    private function rawTokensAfterCommand(ArgvInput $input): array
    {
        try {
            $tokens = (new \ReflectionProperty(ArgvInput::class, 'tokens'))->getValue($input);
        } catch (\ReflectionException) {
            return [];
        }

        if (!\is_array($tokens)) {
            return [];
        }

        $commandName = $input->getFirstArgument();
        $parameters = [];
        $keep = false;
        foreach ($tokens as $value) {
            if (!\is_string($value)) {
                continue;
            }
            if (!$keep) {
                if ($value === $commandName) {
                    $keep = true;
                }

                continue;
            }
            $parameters[] = $value;
        }

        return $parameters;
    }

    /**
     * @param list<string> $tokens
     *
     * @return array{0: string|null, 1: string, 2: string|null, 3: array<string, string|bool|list<string>>}
     */
    private function parseRawTokens(array $tokens): array
    {
        $toolName = null;
        $format = 'pretty';
        $json = null;
        $params = [];

        $count = \count($tokens);
        for ($i = 0; $i < $count; ++$i) {
            $token = $tokens[$i];

            if (!str_starts_with($token, '-')) {
                if (null === $toolName) {
                    $toolName = $token;
                } elseif (null === $json && str_starts_with(trim($token), '{')) {
                    // Backwards-friendly: a bare JSON object positional acts like --json.
                    $json = $token;
                }

                continue;
            }

            if ('--' === $token) {
                // End-of-options separator; the console advertises it in the synopsis.
                continue;
            }

            if (!str_starts_with($token, '--')) {
                // Short flags (e.g. -v) carry no tool parameter meaning.
                continue;
            }

            $name = substr($token, 2);
            $value = null;
            if (str_contains($name, '=')) {
                [$name, $value] = explode('=', $name, 2);
            }

            if (\in_array($name, self::RESERVED_VALUE_OPTIONS, true)) {
                if (null === $value) {
                    $value = $this->takeValue($tokens, $i);
                    if (null === $value) {
                        throw new InvalidArgumentException(\sprintf('The "--%s" option requires a value.', $name));
                    }
                }
                if ('format' === $name) {
                    $format = $value;
                } else {
                    $json = $value;
                }

                continue;
            }

            if (\in_array($name, self::GLOBAL_FLAGS, true)) {
                continue;
            }

            if (null === $value) {
                // No value in sight means a bare flag. Only a boolean parameter can accept that,
                // which the caster decides once the tool is known.
                $value = $this->takeValue($tokens, $i) ?? true;
            }

            if (\array_key_exists($name, $params)) {
                // Repeating an option is how a variadic parameter receives more than one value.
                // Overwriting would drop everything but the last one without saying so.
                $previous = $params[$name];
                $params[$name] = \is_array($previous) ? [...$previous, $value] : [$previous, $value];

                continue;
            }

            $params[$name] = $value;
        }

        return [$toolName, $format, $json, $params];
    }

    /**
     * Consumes the next token as a value when it is not another option, advancing the cursor.
     *
     * A leading `-` normally marks an option, but a negative number is a legitimate value and
     * bridges document relative dates such as `-1 hour`, so those are taken too.
     *
     * @param list<string> $tokens
     */
    private function takeValue(array $tokens, int &$index): ?string
    {
        $next = $tokens[$index + 1] ?? null;
        if (null === $next) {
            return null;
        }

        if (str_starts_with($next, '-') && 1 !== preg_match('/^-\.?\d/', $next)) {
            return null;
        }

        ++$index;

        return $next;
    }

    private function renderPretty(mixed $result, SymfonyStyle $io): void
    {
        if (\is_array($result)) {
            if (array_is_list($result)) {
                foreach ($result as $item) {
                    $io->text($this->formatValue($item));
                }
            } else {
                $io->definitionList(...array_map(fn ($key, $value) => [$key => $this->formatValue($value)], array_keys($result), $result));
            }
        } elseif (\is_string($result)) {
            $io->text($result);
        } elseif (\is_bool($result)) {
            $io->text($result ? 'true' : 'false');
        } elseif (null === $result) {
            $io->text('<comment>null</comment>');
        } else {
            $io->text((string) $result);
        }
    }

    private function formatValue(mixed $value): string
    {
        if (\is_array($value)) {
            return json_encode($value, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
        }

        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (null === $value) {
            return 'null';
        }

        return (string) $value;
    }
}
