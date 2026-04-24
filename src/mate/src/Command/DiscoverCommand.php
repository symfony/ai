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

use Symfony\AI\Mate\Agent\AgentInstructionsMaterializer;
use Symfony\AI\Mate\Discovery\ComposerExtensionDiscovery;
use Symfony\AI\Mate\Service\ExtensionConfigSynchronizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Discover MCP extensions installed via Composer.
 *
 * Scans for packages with extra.ai-mate configuration
 * and generates/updates mate/extensions.php with discovered extensions.
 * Also refreshes AGENT instruction artifacts for coding agents.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
#[AsCommand('discover', 'Discover MCP bridges installed via Composer')]
class DiscoverCommand extends Command
{
    public function __construct(
        private ComposerExtensionDiscovery $extensionDiscovery,
        private ExtensionConfigSynchronizer $extensionConfigSynchronizer,
        private AgentInstructionsMaterializer $instructionsMaterializer,
    ) {
        parent::__construct(self::getDefaultName());
    }

    public static function getDefaultName(): string
    {
        return 'discover';
    }

    public static function getDefaultDescription(): string
    {
        return 'Discover MCP bridges installed via Composer';
    }

    protected function configure(): void
    {
        $this->addOption('composer', null, InputOption::VALUE_NONE, 'Compact output for Composer plugin integration');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $composerMode = $input->getOption('composer');

        $extensions = $this->extensionDiscovery->discover();
        $rootProjectExtension = $this->extensionDiscovery->discoverRootProject();

        if (!$composerMode) {
            $io->title('MCP Extension Discovery');
            $io->text('Scanning for packages with <info>extra.ai-mate</info> configuration...');
            $io->newLine();
        }

        $count = \count($extensions);
        if (0 === $count) {
            $materializationResult = $this->instructionsMaterializer->materializeForExtensions([
                '_custom' => $rootProjectExtension,
            ]);

            if ($composerMode) {
                $io->write('<info>AI Mate:</info> No extensions found.');
            } else {
                $io->warning([
                    'No MCP extensions found.',
                    'Packages must have "extra.ai-mate" configuration in their composer.json.',
                ]);
                $this->displayInstructionsStatus($io, $materializationResult);
                $io->note('Run "composer require vendor/package" to install MCP extensions.');
            }

            return Command::SUCCESS;
        }

        $synchronizationResult = $this->extensionConfigSynchronizer->synchronize($extensions);
        $newPackages = $synchronizationResult['new_packages'];
        $removedPackages = $synchronizationResult['removed_packages'];

        $enabledExtensionsForInstructions = [
            '_custom' => $rootProjectExtension,
        ];

        foreach ($synchronizationResult['extensions'] as $packageName => $config) {
            if (!$config['enabled']) {
                continue;
            }

            if (!isset($extensions[$packageName])) {
                continue;
            }

            $enabledExtensionsForInstructions[$packageName] = $extensions[$packageName];
        }

        $materializationResult = $this->instructionsMaterializer->materializeForExtensions($enabledExtensionsForInstructions);

        if ($composerMode) {
            $this->displayComposerSummary($io, $count, $newPackages, $removedPackages);

            return Command::SUCCESS;
        }

        $io->section(\sprintf('Discovered %d Extension%s', $count, 1 === $count ? '' : 's'));
        $rows = [];
        foreach ($extensions as $packageName => $data) {
            $isNew = \in_array($packageName, $newPackages, true);
            $status = $isNew ? '<fg=green>NEW</>' : '<fg=gray>existing</>';
            $dirCount = \count($data['dirs']);
            $rows[] = [
                $status,
                $packageName,
                \sprintf('%d director%s', $dirCount, 1 === $dirCount ? 'y' : 'ies'),
            ];
        }
        $io->table(['Status', 'Package', 'Scan Directories'], $rows);

        $io->success(\sprintf('Configuration written to: %s', $synchronizationResult['file']));

        if (\count($newPackages) > 0) {
            $io->note(\sprintf('Added %d new extension%s. All extensions are enabled by default.', \count($newPackages), 1 === \count($newPackages) ? '' : 's'));
        }

        if (\count($removedPackages) > 0) {
            $io->warning([
                \sprintf('Removed %d extension%s no longer found:', \count($removedPackages), 1 === \count($removedPackages) ? '' : 's'),
                ...array_map(static fn ($pkg) => '  • '.$pkg, $removedPackages),
            ]);
        }

        $this->displayInstructionsStatus($io, $materializationResult);

        $io->comment([
            'Next steps:',
            '  • Edit mate/extensions.php to enable/disable specific extensions',
        ]);

        return Command::SUCCESS;
    }

    /**
     * @param string[] $newPackages
     * @param string[] $removedPackages
     */
    private function displayComposerSummary(SymfonyStyle $io, int $count, array $newPackages, array $removedPackages): void
    {
        $summary = \sprintf('%d extension%s synchronized', $count, 1 === $count ? '' : 's');

        $details = [];
        if (\count($newPackages) > 0) {
            $details[] = \sprintf('%d new', \count($newPackages));
        }
        if (\count($removedPackages) > 0) {
            $details[] = \sprintf('%d removed', \count($removedPackages));
        }
        if ([] !== $details) {
            $summary .= ' ('.implode(', ', $details).')';
        }

        $io->write('');
        $io->write(\sprintf('<bg=green;fg=white> AI Mate </> %s.', $summary));

        foreach ($newPackages as $package) {
            $io->write(\sprintf('  <fg=green>+</> %s', $package));
        }
        foreach ($removedPackages as $package) {
            $io->write(\sprintf('  <fg=red>-</> %s', $package));
        }

        $io->write('');
    }

    /**
     * @param array{instructions_file_updated: bool, agents_file_updated: bool} $materializationResult
     */
    private function displayInstructionsStatus(SymfonyStyle $io, array $materializationResult): void
    {
        if ($materializationResult['instructions_file_updated']) {
            $io->text('Updated <info>mate/AGENT_INSTRUCTIONS.md</info>.');
        } else {
            $io->warning('Failed to update mate/AGENT_INSTRUCTIONS.md.');
        }

        if ($materializationResult['agents_file_updated']) {
            $io->text('Updated <info>AGENTS.md</info> managed instructions block.');
        } else {
            $io->warning('Failed to update AGENTS.md managed instructions block.');
        }
    }
}
