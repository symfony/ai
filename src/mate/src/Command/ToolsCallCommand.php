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
use Mcp\Capability\Registry;
use Mcp\Capability\Registry\ReferenceHandler;
use Mcp\Exception\ToolNotFoundException;
use Mcp\Schema\Request\CallToolRequest;
use Psr\Log\LoggerInterface;
use Symfony\AI\Mate\Command\Session\CliSession;
use Symfony\AI\Mate\Discovery\FilteredDiscoveryLoader;
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
 * @author Johannes Wachter <johannes@sulu.io>
 */
#[AsCommand('mcp:tools:call', 'Execute MCP tools via JSON input')]
class ToolsCallCommand extends Command
{
    private Registry $registry;
    private ReferenceHandler $referenceHandler;

    public function __construct(
        LoggerInterface $logger,
        private ContainerInterface $container,
    ) {
        parent::__construct(self::getDefaultName());

        $rootDir = $container->getParameter('mate.root_dir');
        \assert(\is_string($rootDir));

        $extensions = $this->container->getParameter('mate.extensions') ?? [];
        \assert(\is_array($extensions));

        $disabledFeatures = $this->container->getParameter('mate.disabled_features') ?? [];
        \assert(\is_array($disabledFeatures));

        $loader = new FilteredDiscoveryLoader(
            basePath: $rootDir,
            extensions: $extensions,
            disabledFeatures: $disabledFeatures,
            discoverer: new Discoverer($logger),
            logger: $logger
        );

        $this->registry = new Registry(null, $logger);
        $loader->load($this->registry);

        $this->referenceHandler = new ReferenceHandler($container);
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
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command executes MCP tools with JSON input parameters.

<info>Usage Examples:</info>

  <comment># Execute a tool with parameters</comment>
  %command.full_name% search-logs '{"query": "error", "level": "error"}'

  <comment># Execute tool with empty parameters</comment>
  %command.full_name% php-version '{}'

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
        $verbose = $output->isVerbose();

        $toolName = $input->getArgument('tool-name');
        \assert(\is_string($toolName));

        $jsonInput = $input->getArgument('json-input');
        \assert(\is_string($jsonInput));

        $params = json_decode($jsonInput, true);
        if (\JSON_ERROR_NONE !== json_last_error()) {
            $io->error(\sprintf('Invalid JSON: %s', json_last_error_msg()));

            return Command::FAILURE;
        }

        if (!\is_array($params)) {
            $io->error('JSON input must be an object');

            return Command::FAILURE;
        }

        try {
            $tool = $this->registry->getTool($toolName);
        } catch (ToolNotFoundException $e) {
            $io->error(\sprintf('Tool "%s" not found', $toolName));
            $io->note('Use "bin/mate.php mcp:tools:list" to see all available tools');

            return Command::FAILURE;
        }

        $session = new CliSession();
        $request = new CallToolRequest(
            name: $toolName,
            arguments: $params
        );

        $arguments = $params;
        $arguments['_session'] = $session;
        $arguments['_request'] = $request;

        $format = $input->getOption('format');
        \assert(\is_string($format));

        if ('pretty' === $format) {
            $io->title(\sprintf('Executing Tool: %s', $toolName));
            if ($tool->tool->description) {
                $io->text($tool->tool->description);
            }
            $io->newLine();
        }

        try {
            $result = $this->referenceHandler->handle($tool, $arguments);
        } catch (\Throwable $e) {
            if ($verbose) {
                $io->error(\sprintf('Error: %s', $e->getMessage()));
                $io->text($e->getTraceAsString());
            } else {
                $io->error(\sprintf('Error: %s', $e->getMessage()));
            }

            return Command::FAILURE;
        }

        if ('json' === $format) {
            $output->writeln(json_encode($result, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
        } else {
            $io->section('Result');
            $this->renderPretty($result, $io);
        }

        return Command::SUCCESS;
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
