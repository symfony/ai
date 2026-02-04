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

use Symfony\AI\Mate\Discovery\ComposerExtensionDiscovery;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Display detailed information about discovered and loaded MCP extensions.
 *
 * @phpstan-import-type ExtensionData from ComposerExtensionDiscovery
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
#[AsCommand('debug:extensions', 'Display detailed information about discovered and loaded MCP extensions')]
class DebugExtensionsCommand extends Command
{
    /**
     * @var array<string, ExtensionData>
     */
    private array $discoveredExtensions;

    /**
     * @var ExtensionData
     */
    private array $rootProjectConfig;

    /**
     * @var string[]
     */
    private array $enabledExtensions;

    /**
     * @var array<string, ExtensionData>
     */
    private array $extensions;

    /**
     * @param string[]                     $enabledExtensions
     * @param array<string, ExtensionData> $extensions
     */
    public function __construct(
        array $enabledExtensions,
        array $extensions,
        private ComposerExtensionDiscovery $extensionDiscovery,
    ) {
        parent::__construct(self::getDefaultName());

        $this->enabledExtensions = $enabledExtensions;
        $this->extensions = $extensions;
        $this->discoveredExtensions = $this->extensionDiscovery->discover();
        $this->rootProjectConfig = $this->extensionDiscovery->discoverRootProject();
    }

    public static function getDefaultName(): string
    {
        return 'debug:extensions';
    }

    public static function getDefaultDescription(): string
    {
        return 'Display detailed information about discovered and loaded MCP extensions';
    }

    protected function configure(): void
    {
        $this
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format (text, json)', 'text')
            ->addOption('show-all', null, InputOption::VALUE_NONE, 'Show all discovered extensions including disabled ones')
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command displays detailed information about MCP extension
discovery and loading.

<info>Usage Examples:</info>

  <comment># Show enabled extensions</comment>
  %command.full_name%

  <comment># Show all discovered extensions (including disabled)</comment>
  %command.full_name% --show-all

  <comment># JSON output for scripting</comment>
  %command.full_name% --format=json

<info>Extension Information:</info>

  • Discovery status (discovered vs enabled)
  • Scan directories being monitored
  • Include files being loaded
  • Whether extension provides any capabilities
  • Configuration source (composer.json extra.ai-mate)

<info>Extension Types:</info>

  • <fg=green>Enabled</>: Active and loaded into container
  • <fg=yellow>Disabled</>: Discovered but disabled in mate/extensions.php
  • <fg=cyan>Root Project</>: Project-specific capabilities from mate/src/
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $showAll = $input->getOption('show-all');
        $format = $input->getOption('format');
        \assert(\is_string($format));

        if ('json' === $format) {
            $this->outputJson($output);
        } else {
            $this->outputText($showAll, $io);
        }

        return Command::SUCCESS;
    }

    private function outputText(bool $showAll, SymfonyStyle $io): void
    {
        $io->title('MCP Extension Discovery');

        $io->section('Root Project');
        $this->displayExtensionDetails($io, '_custom', $this->rootProjectConfig, true, true);

        $enabledCount = 0;
        $disabledCount = 0;

        $enabledExtensions = [];
        $disabledExtensions = [];

        foreach ($this->discoveredExtensions as $packageName => $data) {
            $isEnabled = \in_array($packageName, $this->enabledExtensions, true);

            if ($isEnabled) {
                ++$enabledCount;
                $enabledExtensions[$packageName] = $data;
            } else {
                ++$disabledCount;
                $disabledExtensions[$packageName] = $data;
            }
        }

        if (\count($enabledExtensions) > 0) {
            $io->section(\sprintf('Enabled Extensions (%d)', $enabledCount));
            foreach ($enabledExtensions as $packageName => $data) {
                $isLoaded = isset($this->extensions[$packageName]);
                $this->displayExtensionDetails($io, $packageName, $data, true, $isLoaded);
            }
        }

        if ($showAll && \count($disabledExtensions) > 0) {
            $io->section(\sprintf('Disabled Extensions (%d)', $disabledCount));
            foreach ($disabledExtensions as $packageName => $data) {
                $isLoaded = isset($this->extensions[$packageName]);
                $this->displayExtensionDetails($io, $packageName, $data, false, $isLoaded);
            }
        }

        $io->section('Summary');
        $io->text(\sprintf('Total discovered: %d', \count($this->discoveredExtensions) + 1)); // +1 for root project
        $io->text(\sprintf('Enabled: %d', $enabledCount + 1)); // +1 for root project
        $io->text(\sprintf('Disabled: %d', $disabledCount));
        $io->text(\sprintf('Loaded: %d', \count($this->extensions)));

        if (!$showAll && $disabledCount > 0) {
            $io->note(\sprintf('Use --show-all to see %d disabled extension%s', $disabledCount, 1 === $disabledCount ? '' : 's'));
        }
    }

    /**
     * @param ExtensionData $data
     */
    private function displayExtensionDetails(
        SymfonyStyle $io,
        string $packageName,
        array $data,
        bool $isEnabled,
        bool $isLoaded,
    ): void {
        $status = $isEnabled ? '<fg=green>enabled</>' : '<fg=yellow>disabled</>';
        $loadedStatus = $isLoaded ? '<fg=green>loaded</>' : '<fg=red>not loaded</>';

        $io->text(\sprintf('<info>%s</info> [%s] [%s]', $packageName, $status, $loadedStatus));

        if (\count($data['dirs']) > 0) {
            $io->text(\sprintf('  Scan directories (%d):', \count($data['dirs'])));
            foreach ($data['dirs'] as $dir) {
                $io->text(\sprintf('    • %s', $dir));
            }
        } else {
            $io->text('  <fg=gray>No scan directories</>');
        }

        if (\count($data['includes']) > 0) {
            $io->text(\sprintf('  Include files (%d):', \count($data['includes'])));
            foreach ($data['includes'] as $file) {
                $io->text(\sprintf('    • %s', $file));
            }
        } else {
            $io->text('  <fg=gray>No include files</>');
        }

        if (isset($data['instructions'])) {
            $io->text(\sprintf('  Agent instructions: <fg=cyan>%s</>', $data['instructions']));
        } else {
            $io->text('  <fg=gray>No agent instructions</>');
        }

        $io->newLine();
    }

    private function outputJson(OutputInterface $output): void
    {
        $extensions = [];

        $rootExtension = [
            'type' => 'root_project',
            'status' => 'enabled',
            'loaded' => true,
            'scan_dirs' => $this->rootProjectConfig['dirs'],
            'includes' => $this->rootProjectConfig['includes'],
        ];

        if (isset($this->rootProjectConfig['instructions'])) {
            $rootExtension['agent_instructions'] = $this->rootProjectConfig['instructions'];
        }

        $extensions['_custom'] = $rootExtension;

        foreach ($this->discoveredExtensions as $packageName => $data) {
            $isEnabled = \in_array($packageName, $this->enabledExtensions, true);
            $isLoaded = isset($this->extensions[$packageName]);

            $extensionData = [
                'type' => 'vendor_extension',
                'status' => $isEnabled ? 'enabled' : 'disabled',
                'loaded' => $isLoaded,
                'scan_dirs' => $data['dirs'],
                'includes' => $data['includes'],
            ];

            if (isset($data['instructions'])) {
                $extensionData['agent_instructions'] = $data['instructions'];
            }

            $extensions[$packageName] = $extensionData;
        }

        $enabledCount = array_reduce($extensions, static fn ($count, $ext) => 'enabled' === $ext['status'] ? $count + 1 : $count, 0);
        $disabledCount = \count($extensions) - $enabledCount;
        $loadedCount = array_reduce($extensions, static fn ($count, $ext) => $ext['loaded'] ? $count + 1 : $count, 0);

        $result = [
            'extensions' => $extensions,
            'summary' => [
                'total_discovered' => \count($extensions),
                'enabled' => $enabledCount,
                'disabled' => $disabledCount,
                'loaded' => $loadedCount,
            ],
        ];

        $output->writeln(json_encode($result, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
    }
}
