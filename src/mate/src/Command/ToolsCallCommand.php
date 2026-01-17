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

use Mcp\Capability\Discovery\Discoverer;
use Psr\Log\LoggerInterface;
use Symfony\AI\Mate\Discovery\CapabilityCollector;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Execute MCP tools via JSON input.
 *
 * @phpstan-import-type Capabilities from CapabilityCollector
 *
 * @phpstan-type ToolData array{
 *     name: string,
 *     description: string|null,
 *     handler: string,
 *     input_schema: array<string, mixed>|null,
 *     extension: string
 * }
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
#[AsCommand('mcp:tools:call', 'Execute MCP tools via JSON input')]
class ToolsCallCommand extends Command
{
    private CapabilityCollector $collector;

    /**
     * @var array<string, array{dirs: string[], includes: string[]}>
     */
    private array $extensions;

    public function __construct(
        LoggerInterface $logger,
        private ContainerInterface $container,
    ) {
        parent::__construct(self::getDefaultName());

        $rootDir = $container->getParameter('mate.root_dir');
        \assert(\is_string($rootDir));

        $extensions = $this->container->getParameter('mate.extensions') ?? [];
        \assert(\is_array($extensions));
        $this->extensions = $extensions;

        $disabledFeatures = $this->container->getParameter('mate.disabled_features') ?? [];
        \assert(\is_array($disabledFeatures));

        $this->collector = new CapabilityCollector($rootDir, $extensions, $disabledFeatures, new Discoverer($logger), $logger);
    }

    public static function getDefaultName(): string
    {
        return 'mcp:tools:call';
    }

    public static function getDefaultDescription(): string
    {
        return 'Execute MCP tools via JSON input';
    }

    protected function configure(): void
    {
        $this
            ->addArgument('tool-name', InputArgument::REQUIRED, 'Name of the tool to execute')
            ->addArgument('json-input', InputArgument::REQUIRED, 'JSON object with tool parameters')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format (json, pretty)', 'pretty')
            ->addOption('validate-only', null, InputOption::VALUE_NONE, 'Only validate input, do not execute')
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command executes MCP tools with JSON input parameters.

<info>Usage Examples:</info>

  <comment># Execute a tool with parameters</comment>
  %command.full_name% search-logs '{"query": "error", "level": "error"}'

  <comment># Execute tool with empty parameters</comment>
  %command.full_name% php-version '{}'

  <comment># Validate input without execution</comment>
  %command.full_name% search-logs '{"query": "test"}' --validate-only

  <comment># JSON output format</comment>
  %command.full_name% php-version '{}' --format=json

  <comment># For a list of available tools, use:</comment>
  bin/mate.php mcp:tools:list

  <comment># For detailed tool information and schema, use:</comment>
  bin/mate.php mcp:tools:inspect <tool-name>
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $toolName = $input->getArgument('tool-name');
        \assert(\is_string($toolName));

        $jsonInput = $input->getArgument('json-input');
        \assert(\is_string($jsonInput));

        $allTools = [];
        foreach ($this->extensions as $extensionName => $extension) {
            $capabilities = $this->collector->collectCapabilities($extensionName, $extension);
            foreach ($capabilities['tools'] as $name => $toolData) {
                $allTools[$name] = array_merge($toolData, ['extension' => $extensionName]);
            }
        }

        if (!isset($allTools[$toolName])) {
            $io->error(\sprintf('Tool "%s" not found', $toolName));
            $io->note('Use "bin/mate.php mcp:tools:list" to see all available tools');

            return Command::FAILURE;
        }

        $toolData = $allTools[$toolName];

        $params = json_decode($jsonInput, true);
        if (\JSON_ERROR_NONE !== json_last_error()) {
            $io->error(\sprintf('Invalid JSON: %s', json_last_error_msg()));

            return Command::FAILURE;
        }

        if (!\is_array($params)) {
            $io->error('JSON input must be an object');

            return Command::FAILURE;
        }

        $validationErrors = $this->validateAgainstSchema($params, $toolData['input_schema']);
        if ([] !== $validationErrors) {
            $io->error('Validation errors:');
            foreach ($validationErrors as $error) {
                $io->text('  - '.$error);
            }

            return Command::FAILURE;
        }

        if ($input->getOption('validate-only')) {
            $io->success('Validation successful');

            return Command::SUCCESS;
        }

        $format = $input->getOption('format');
        \assert(\is_string($format));

        if ('pretty' === $format) {
            $io->title(\sprintf('Executing Tool: %s', $toolName));
            if (null !== $toolData['description']) {
                $io->text($toolData['description']);
            }
            $io->text(\sprintf('<info>Extension:</info> %s', $toolData['extension']));
            $io->newLine();
        }

        [$className, $methodName] = $this->parseHandler($toolData['handler']);

        if (!$this->container->has($className)) {
            $io->error(\sprintf('Handler class "%s" not found in container', $className));

            return Command::FAILURE;
        }

        $handlerInstance = $this->container->get($className);

        if (!method_exists($handlerInstance, $methodName)) {
            $io->error(\sprintf('Method "%s" not found in class "%s"', $methodName, $className));

            return Command::FAILURE;
        }

        $args = $this->mapParamsToArgs($params, $toolData['input_schema']);
        $result = $handlerInstance->$methodName(...$args);

        // Display result
        $format = $input->getOption('format');
        \assert(\is_string($format));

        if ('json' === $format) {
            // For JSON format, output pure JSON without decorative headers
            $output->writeln(json_encode($result, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
        } else {
            $io->section('Result');
            $this->renderPretty($result, $io);
        }

        return Command::SUCCESS;
    }

    /**
     * @return array{string, string}
     */
    private function parseHandler(string $handler): array
    {
        if (str_contains($handler, '::')) {
            $parts = explode('::', $handler, 2);

            return [$parts[0], $parts[1]];
        }

        return [$handler, '__invoke'];
    }

    /**
     * @param array<string, mixed>      $params
     * @param array<string, mixed>|null $schema
     *
     * @return string[]
     */
    private function validateAgainstSchema(array $params, ?array $schema): array
    {
        $errors = [];

        if (null === $schema) {
            return $errors;
        }

        if (isset($schema['required']) && \is_array($schema['required'])) {
            foreach ($schema['required'] as $field) {
                if (!\array_key_exists($field, $params)) {
                    $errors[] = \sprintf('Missing required field: %s', $field);
                }
            }
        }

        if (isset($schema['properties']) && \is_array($schema['properties'])) {
            foreach ($params as $key => $value) {
                if (isset($schema['properties'][$key]['type'])) {
                    $expectedType = $schema['properties'][$key]['type'];
                    if (!\is_string($expectedType)) {
                        continue;
                    }
                    if (!$this->validateType($value, $expectedType)) {
                        $errors[] = \sprintf('Invalid type for %s: expected %s', $key, $expectedType);
                    }
                }
            }
        }

        return $errors;
    }

    private function validateType(mixed $value, string $expectedType): bool
    {
        return match ($expectedType) {
            'string' => \is_string($value),
            'integer' => \is_int($value),
            'number' => \is_int($value) || \is_float($value),
            'boolean' => \is_bool($value),
            'array' => \is_array($value),
            'object' => \is_array($value),
            'null' => null === $value,
            default => true,
        };
    }

    /**
     * @param array<string, mixed>      $params
     * @param array<string, mixed>|null $schema
     *
     * @return array<int, mixed>
     */
    private function mapParamsToArgs(array $params, ?array $schema): array
    {
        if (null === $schema || !isset($schema['properties']) || !\is_array($schema['properties'])) {
            return [];
        }

        $args = [];
        foreach ($schema['properties'] as $paramName => $propertySchema) {
            if (\array_key_exists($paramName, $params)) {
                $args[] = $params[$paramName];
            } elseif (isset($propertySchema['default'])) {
                $args[] = $propertySchema['default'];
            } else {
                $args[] = null;
            }
        }

        return $args;
    }

    private function renderPretty(mixed $result, SymfonyStyle $io): void
    {
        if (\is_array($result)) {
            if ($this->isAssociativeArray($result)) {
                $io->definitionList(...array_map(fn ($key, $value) => [$key => $this->formatValue($value)], array_keys($result), $result));
            } else {
                foreach ($result as $item) {
                    $io->text($this->formatValue($item));
                }
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

    /**
     * @param array<mixed> $array
     */
    private function isAssociativeArray(array $array): bool
    {
        if ([] === $array) {
            return false;
        }

        return array_keys($array) !== range(0, \count($array) - 1);
    }

    private function formatValue(mixed $value): string
    {
        if (\is_array($value)) {
            return json_encode($value, \JSON_UNESCAPED_SLASHES);
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
