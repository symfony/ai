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
use Symfony\AI\Mate\Command\Trait\RendersToolResultTrait;
use Symfony\AI\Mate\Discovery\CapabilityRegistry;
use Symfony\AI\Mate\Encoding\ResponseEncoder;
use Symfony\AI\Mate\Exception\ResourceNotFoundException;
use Symfony\AI\Mate\Invocation\ResourceReader;
use Symfony\AI\Mate\Invocation\ToolInvoker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Execute several tools and resource reads in one command call, so an agent that needs
 * multiple results for a single investigation pays for one round-trip instead of N.
 *
 * Each call is dispatched sequentially: Mate tool handlers are plain synchronous PHP
 * calls, and building real concurrent dispatch is out of scope for this command. One
 * call failing (unknown tool or resource, thrown exception) does not abort the others; each result
 * is reported independently so partial results are still useful.
 *
 * @phpstan-type BatchCall array{tool?: string, params?: array<string, mixed>, resource?: string}
 * @phpstan-type BatchResult array{tool?: string, resource?: string, ok: bool, result: mixed, error: string|null}
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
#[AsCommand('tools:call-batch', 'Execute several tools in one command call')]
class ToolsCallBatchCommand extends Command
{
    use EnsuresToonFormatAvailabilityTrait;
    use RendersToolResultTrait;

    public function __construct(
        private CapabilityRegistry $registry,
        private ToolInvoker $invoker,
        private ResourceReader $reader,
    ) {
        parent::__construct(self::getDefaultName());
    }

    public static function getDefaultName(): string
    {
        return 'tools:call-batch';
    }

    public static function getDefaultDescription(): string
    {
        return 'Execute several tools in one command call';
    }

    protected function configure(): void
    {
        $script = $_SERVER['PHP_SELF'] ?? 'vendor/bin/mate';

        $this
            ->addOption('json', null, InputOption::VALUE_REQUIRED, 'A JSON array of {"tool": "...", "params": {...}} or {"resource": "<uri>"} call objects')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format (json, pretty, toon)', 'pretty')
            ->setHelp(
                <<<HELP
The <info>%command.name%</info> command executes several tools and resource reads in one call, saving the
round-trips that calling <info>tools:call</info> or <info>resources:read</info> once per item would cost. Calls run sequentially and one
failing call does not abort the others.

<info>Usage Examples:</info>

  <comment># Run two tools and correlate their results</comment>
  %command.full_name% --json='[{"tool": "symfony-profiler-get", "params": {"token": "abc123"}}, {"tool": "monolog-search", "params": {"level": "ERROR"}}]'

  <comment># Mix tool calls and resource reads</comment>
  %command.full_name% --json='[{"tool": "symfony-profiler-list", "params": {"limit": 1}}, {"resource": "symfony-profiler://profile/abc123/db"}]'

  <comment># JSON output format for scripting</comment>
  %command.full_name% --json='[{"tool": "server-info"}]' --format=json

  <comment># For a single tool call, use:</comment>
  {$script} tools:call <tool-name>
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $format = $input->getOption('format');
        $format = \is_string($format) ? $format : 'pretty';

        if (!$this->ensureFormatSupported($io, $format, ['pretty', 'json', 'toon'])) {
            return Command::FAILURE;
        }

        $jsonOption = $input->getOption('json');
        if (!\is_string($jsonOption) || '' === $jsonOption) {
            $io->error('Not enough arguments (missing: "--json").');

            return Command::FAILURE;
        }

        $decoded = json_decode($jsonOption, true);
        if (\JSON_ERROR_NONE !== json_last_error()) {
            $io->error(\sprintf('Invalid JSON in --json: %s', json_last_error_msg()));

            return Command::FAILURE;
        }

        $validationError = $this->validateCalls($decoded);
        if (null !== $validationError) {
            $io->error($validationError);

            return Command::FAILURE;
        }

        /** @var list<BatchCall> $decoded */
        $results = $this->dispatch($decoded);

        if ('json' === $format) {
            $output->writeln(json_encode($results, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
        } elseif ('toon' === $format) {
            $output->writeln(Toon::encode($results));
        } else {
            $this->renderResultsPretty($results, $io, $output);
        }

        foreach ($results as $result) {
            if (!$result['ok']) {
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Validates the decoded --json payload without executing anything, so a malformed
     * request is reported as one clear error instead of one confusing error per call.
     */
    private function validateCalls(mixed $decoded): ?string
    {
        if (!\is_array($decoded) || !array_is_list($decoded)) {
            return 'The --json value must be a JSON array of call objects.';
        }

        if ([] === $decoded) {
            return 'The batch must contain at least one call.';
        }

        foreach ($decoded as $index => $call) {
            if (!\is_array($call)) {
                return \sprintf('Call at index %d must be an object with a "tool" or a "resource".', $index);
            }

            if ([] !== $unknown = array_diff(array_keys($call), ['tool', 'params', 'resource'])) {
                return \sprintf('Call at index %d has unknown key "%s", only "tool", "params" and "resource" are allowed.', $index, implode('", "', array_map(strval(...), $unknown)));
            }

            $hasTool = isset($call['tool']);
            $hasResource = isset($call['resource']);

            if ($hasTool === $hasResource) {
                return \sprintf('Call at index %d needs exactly one of "tool" or "resource".', $index);
            }

            if ($hasResource) {
                if (!\is_string($call['resource']) || '' === $call['resource']) {
                    return \sprintf('Call at index %d has an empty "resource" URI.', $index);
                }

                if (isset($call['params'])) {
                    return \sprintf('Call at index %d ("%s") is a resource read and cannot have "params".', $index, $call['resource']);
                }

                continue;
            }

            if (!\is_string($call['tool']) || '' === $call['tool']) {
                return \sprintf('Call at index %d is missing a "tool" name.', $index);
            }

            if (isset($call['params']) && !\is_array($call['params'])) {
                return \sprintf('Call at index %d ("%s") has a non-object "params" value.', $index, $call['tool']);
            }
        }

        return null;
    }

    /**
     * @param list<BatchCall> $calls
     *
     * @return list<BatchResult>
     */
    private function dispatch(array $calls): array
    {
        $results = [];
        foreach ($calls as $call) {
            $results[] = isset($call['resource'])
                ? $this->readResource($call['resource'])
                : $this->dispatchOne($call['tool'] ?? '', $call['params'] ?? []);
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return BatchResult
     */
    private function dispatchOne(string $toolName, array $params): array
    {
        $tool = $this->registry->findTool($toolName);
        if (null === $tool) {
            return ['tool' => $toolName, 'ok' => false, 'result' => null, 'error' => \sprintf('Tool "%s" not found', $toolName)];
        }

        try {
            $result = $this->invoker->invoke($tool, $params);
        } catch (\Throwable $e) {
            return ['tool' => $toolName, 'ok' => false, 'result' => null, 'error' => $e->getMessage()];
        }

        if (\is_string($result)) {
            $result = ResponseEncoder::tryDecode($result);
        }

        return ['tool' => $toolName, 'ok' => true, 'result' => $result, 'error' => null];
    }

    /**
     * @return BatchResult
     */
    private function readResource(string $uri): array
    {
        try {
            $read = $this->reader->read($uri);
        } catch (ResourceNotFoundException) {
            return ['resource' => $uri, 'ok' => false, 'result' => null, 'error' => \sprintf('Resource "%s" not found', $uri)];
        } catch (\Throwable $e) {
            return ['resource' => $uri, 'ok' => false, 'result' => null, 'error' => $e->getMessage()];
        }

        $contents = [];
        foreach ($read['contents'] as $content) {
            $contents[] = isset($content['text']) ? ResponseEncoder::tryDecode($content['text']) : $content;
        }

        return ['resource' => $uri, 'ok' => true, 'result' => 1 === \count($contents) ? $contents[0] : $contents, 'error' => null];
    }

    /**
     * @param list<BatchResult> $results
     */
    private function renderResultsPretty(array $results, SymfonyStyle $io, OutputInterface $output): void
    {
        foreach ($results as $result) {
            $io->section($result['tool'] ?? $result['resource'] ?? '');

            if (!$result['ok']) {
                $io->error($result['error'] ?? 'Unknown error');
            } elseif ($this->isTooLargeToRenderPretty($result['result'])) {
                $this->renderJsonFallback($result['result'], $io, $output);
            } else {
                $this->renderPretty($result['result'], $io);
            }
        }

        $failed = array_filter($results, static fn (array $result): bool => !$result['ok']);
        $io->newLine();
        $io->text(\sprintf('%d of %d calls succeeded.', \count($results) - \count($failed), \count($results)));
    }
}
