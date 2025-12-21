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
use Symfony\AI\Mate\Discovery\ComposerTypeDiscovery;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Discover MCP bridges installed via Composer.
 *
 * Scans for packages with extra.ai-mate configuration
 * and generates/updates .mate/bridges.php with discovered bridges.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class DiscoverCommand extends Command
{
    public function __construct(
        private string $rootDir,
        private LoggerInterface $logger,
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

        $io->title('MCP Bridge Discovery');
        $io->text('Scanning for packages with <info>extra.ai-mate</info> configuration...');
        $io->newLine();

        $discovery = new ComposerTypeDiscovery($this->rootDir, $this->logger);

        $bridges = $discovery->discover([]);

        $count = \count($bridges);
        if (0 === $count) {
            $io->warning([
                'No MCP bridges found.',
                'Packages must have "extra.ai-mate" configuration in their composer.json.',
            ]);
            $io->note('Run "composer require vendor/package" to install MCP bridges.');

            return Command::SUCCESS;
        }

        $bridgesFile = $this->rootDir.'/.mate/bridges.php';
        $existingBridges = [];
        $newPackages = [];
        $removedPackages = [];
        if (file_exists($bridgesFile)) {
            $existingBridges = include $bridgesFile;
            if (!\is_array($existingBridges)) {
                $existingBridges = [];
            }
        }

        foreach ($bridges as $packageName => $data) {
            if (!isset($existingBridges[$packageName])) {
                $newPackages[] = $packageName;
            }
        }

        foreach ($existingBridges as $packageName => $data) {
            if (!isset($bridges[$packageName])) {
                $removedPackages[] = $packageName;
            }
        }

        $io->section(\sprintf('Discovered %d Bridge%s', $count, 1 === $count ? '' : 's'));
        $rows = [];
        foreach ($bridges as $packageName => $data) {
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

        $finalBridges = [];
        foreach ($bridges as $packageName => $data) {
            $enabled = true;
            if (isset($existingBridges[$packageName]) && \is_array($existingBridges[$packageName])) {
                $enabled = $existingBridges[$packageName]['enabled'] ?? true;
                if (!\is_bool($enabled)) {
                    $enabled = true;
                }
            }

            $finalBridges[$packageName] = [
                'enabled' => $enabled,
            ];
        }

        $this->writeBridgesFile($bridgesFile, $finalBridges);

        $io->success(\sprintf('Configuration written to: %s', $bridgesFile));

        if (\count($newPackages) > 0) {
            $io->note(\sprintf('Added %d new bridge%s. All bridges are enabled by default.', \count($newPackages), 1 === \count($newPackages) ? '' : 's'));
        }

        if (\count($removedPackages) > 0) {
            $io->warning([
                \sprintf('Removed %d bridge%s no longer found:', \count($removedPackages), 1 === \count($removedPackages) ? '' : 's'),
                ...array_map(fn ($pkg) => '  • '.$pkg, $removedPackages),
            ]);
        }

        $io->comment([
            'Next steps:',
            '  • Edit .mate/bridges.php to enable/disable specific bridges',
            '  • Run "vendor/bin/mate serve" to start the MCP server',
        ]);

        return Command::SUCCESS;
    }

    /**
     * @param array<string, array{enabled: bool}> $bridges
     */
    private function writeBridgesFile(string $filePath, array $bridges): void
    {
        $dir = \dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $content = "<?php\n\n";
        $content .= "// This file is managed by 'mate discover'\n";
        $content .= "// You can manually edit to enable/disable bridges\n\n";
        $content .= "return [\n";

        foreach ($bridges as $packageName => $config) {
            $enabled = $config['enabled'] ? 'true' : 'false';
            $content .= "    '$packageName' => ['enabled' => $enabled],\n";
        }

        $content .= "];\n";

        file_put_contents($filePath, $content);
    }
}
