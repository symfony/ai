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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('MCP Extension Discovery');
        $io->text('Scanning for packages with <info>extra.ai-mate</info> configuration...');
        $io->newLine();

        $extensions = $this->extensionDiscovery->discover();
        $rootProjectExtension = $this->extensionDiscovery->discoverRootProject();

        $count = \count($extensions);
        if (0 === $count) {
            $materializationResult = $this->instructionsMaterializer->materializeForExtensions([
                '_custom' => $rootProjectExtension,
            ]);

            $io->warning([
                'No MCP extensions found.',
                'Packages must have "extra.ai-mate" configuration in their composer.json.',
            ]);
            $this->displayInstructionsStatus($io, $materializationResult);
            $io->note('Run "composer require vendor/package" to install MCP extensions.');

            return Command::SUCCESS;
        }

        $synchronizationResult = $this->extensionConfigSynchronizer->synchronize($extensions);
        $newPackages = $synchronizationResult['new_packages'];
        $removedPackages = $synchronizationResult['removed_packages'];

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
        $this->displayInstructionsStatus($io, $materializationResult);

        $io->comment([
            'Next steps:',
            '  • Edit mate/extensions.php to enable/disable specific extensions',
            '  • Run "vendor/bin/mate serve" to start the MCP server',
        ]);

        return Command::SUCCESS;
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
