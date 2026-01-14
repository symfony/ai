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
 * Display detailed information about a specific MCP tool.
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
#[AsCommand('mcp:tools:inspect', 'Display detailed information about a specific MCP tool')]
class ToolsInspectCommand extends Command
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

        $this->collector = new CapabilityCollector($rootDir, $extensions, $disabledFeatures, $logger);
    }

    public static function getDefaultName(): string
    {
        return 'mcp:tools:inspect';
    }

    public static function getDefaultDescription(): string
    {
        return 'Display detailed information about a specific MCP tool';
    }

    protected function configure(): void
    {
        $this
            ->addArgument('tool-name', InputArgument::REQUIRED, 'Name of the tool to inspect')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format (text, json)', 'text')
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command displays detailed information about a specific MCP tool including its full JSON schema.

<info>Usage Examples:</info>

  <comment># Inspect a tool</comment>
  %command.full_name% php-version

  <comment># JSON output for scripting</comment>
  %command.full_name% php-version --format=json

  <comment># Inspect extension tool</comment>
  %command.full_name% search-logs

  <comment># For a list of all available tools, use:</comment>
  bin/mate.php mcp:tools:list
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $toolName = $input->getArgument('tool-name');
        \assert(\is_string($toolName));

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

        $format = $input->getOption('format');
        \assert(\is_string($format));

        if ('json' === $format) {
            $this->outputJson($toolData, $output);

            return Command::SUCCESS;
        }

        $this->outputText($toolData, $io);

        return Command::SUCCESS;
    }

    /**
     * @param ToolData $toolData
     */
    private function outputText(array $toolData, SymfonyStyle $io): void
    {
        $io->title($toolData['name']);

        $io->definitionList(
            ['Description' => $toolData['description'] ?? '<comment>N/A</comment>'],
            ['Handler' => $toolData['handler']],
            ['Extension' => $toolData['extension']],
        );

        if (null !== $toolData['input_schema']) {
            $io->section('Input Schema');
            $io->text(json_encode($toolData['input_schema'], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));

            return;
        }

        $io->section('Input Schema');
        $io->text('<comment>No input schema defined</comment>');
    }

    /**
     * @param ToolData $toolData
     */
    private function outputJson(array $toolData, OutputInterface $output): void
    {
        $output->writeln(json_encode($toolData, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
    }
}
