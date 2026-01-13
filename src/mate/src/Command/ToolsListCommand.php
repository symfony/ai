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
use Symfony\AI\Mate\Exception\InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Display available MCP tools with metadata.
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
#[AsCommand('mcp:tools:list', 'Display available MCP tools with metadata')]
class ToolsListCommand extends Command
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

        $discoverer = new Discoverer($logger);
        $this->collector = new CapabilityCollector($rootDir, $extensions, $disabledFeatures, $discoverer, $logger);
    }

    public static function getDefaultName(): string
    {
        return 'mcp:tools:list';
    }

    public static function getDefaultDescription(): string
    {
        return 'Display available MCP tools with metadata';
    }

    protected function configure(): void
    {
        $this
            ->addOption('filter', null, InputOption::VALUE_REQUIRED, 'Filter by tool name pattern (supports wildcards)')
            ->addOption('extension', null, InputOption::VALUE_REQUIRED, 'Filter by extension package name')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format (table, json)', 'table')
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command displays all available MCP tools with their metadata.

<info>Usage Examples:</info>

  <comment># Show all tools</comment>
  %command.full_name%

  <comment># Filter by tool name pattern</comment>
  %command.full_name% --filter="search*"
  %command.full_name% --filter="*logs"

  <comment># Show tools from specific extension</comment>
  %command.full_name% --extension=symfony/ai-monolog-mate-extension

  <comment># JSON output for scripting</comment>
  %command.full_name% --format=json

  <comment># Combined filters</comment>
  %command.full_name% --extension=symfony/ai-monolog-mate-extension --filter="search*"

  <comment># For detailed tool information with schema, use:</comment>
  bin/mate.php mcp:tools:inspect <tool-name>
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $allTools = [];
        foreach ($this->extensions as $extensionName => $extension) {
            $capabilities = $this->collector->collectCapabilities($extensionName, $extension);
            foreach ($capabilities['tools'] as $toolName => $toolData) {
                $allTools[$toolName] = array_merge($toolData, ['extension' => $extensionName]);
            }
        }

        $extensionFilter = $input->getOption('extension');
        if (null !== $extensionFilter) {
            \assert(\is_string($extensionFilter));
            $allTools = $this->filterByExtension($allTools, $extensionFilter);
        }

        $nameFilter = $input->getOption('filter');
        if (null !== $nameFilter) {
            \assert(\is_string($nameFilter));
            $allTools = $this->filterByName($allTools, $nameFilter);
        }

        $format = $input->getOption('format');
        \assert(\is_string($format));

        if ('json' === $format) {
            $this->outputJson($allTools, $output);

            return Command::SUCCESS;
        }

        $this->outputTable($allTools, $io);

        return Command::SUCCESS;
    }

    /**
     * @param array<string, ToolData> $tools
     *
     * @return array<string, ToolData>
     */
    private function filterByExtension(array $tools, string $extensionFilter): array
    {
        $filtered = [];
        foreach ($tools as $toolName => $toolData) {
            if ($toolData['extension'] === $extensionFilter) {
                $filtered[$toolName] = $toolData;
            }
        }

        if ([] === $filtered) {
            $availableExtensions = array_unique(array_column($tools, 'extension'));
            throw new InvalidArgumentException(\sprintf('No tools found for extension "%s". Available extensions: "%s"', $extensionFilter, implode(', ', $availableExtensions)));
        }

        return $filtered;
    }

    /**
     * @param array<string, ToolData> $tools
     *
     * @return array<string, ToolData>
     */
    private function filterByName(array $tools, string $pattern): array
    {
        $regex = '/^'.str_replace(['\*', '\?'], ['.*', '.'], preg_quote($pattern, '/')).'$/i';

        $filtered = [];
        foreach ($tools as $toolName => $toolData) {
            if (preg_match($regex, $toolName)) {
                $filtered[$toolName] = $toolData;
            }
        }

        if ([] === $filtered) {
            throw new InvalidArgumentException(\sprintf('No tools found matching pattern "%s"', $pattern));
        }

        return $filtered;
    }

    /**
     * @param array<string, ToolData> $tools
     */
    private function outputTable(array $tools, SymfonyStyle $io): void
    {
        $io->title('MCP Tools');

        if ([] === $tools) {
            $io->warning('No tools found');

            return;
        }

        $table = new Table($output = $io);
        $table->setHeaders(['Tool Name', 'Description', 'Handler', 'Extension']);

        foreach ($tools as $toolName => $toolData) {
            $table->addRow([
                $toolName,
                $this->truncate($toolData['description'] ?? '', 50),
                $toolData['handler'],
                $toolData['extension'],
            ]);
        }

        $table->render();

        $io->newLine();
        $io->text(\sprintf('Total: <info>%d</info> tool(s)', \count($tools)));
    }

    /**
     * @param array<string, ToolData> $tools
     */
    private function outputJson(array $tools, OutputInterface $output): void
    {
        $result = [
            'tools' => $tools,
            'summary' => [
                'total' => \count($tools),
            ],
        ];

        $output->writeln(json_encode($result, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
    }

    private function truncate(string $text, int $length): string
    {
        if (\strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length - 3).'...';
    }
}
