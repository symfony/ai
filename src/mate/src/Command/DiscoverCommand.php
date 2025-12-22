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
 * Discover MCP extensions installed via Composer.
 *
 * Scans for packages with extra.ai-mate configuration
 * and generates/updates .mate/extensions.php with discovered extensions.
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

        $io->title('MCP Extension Discovery');
        $io->text('Scanning for packages with <info>extra.ai-mate</info> configuration...');
        $io->newLine();

        $discovery = new ComposerTypeDiscovery($this->rootDir, $this->logger);

        $extensions = $discovery->discover([]);

        $count = \count($extensions);
        if (0 === $count) {
            $io->warning([
                'No MCP extensions found.',
                'Packages must have "extra.ai-mate" configuration in their composer.json.',
            ]);
            $io->note('Run "composer require vendor/package" to install MCP extensions.');

            return Command::SUCCESS;
        }

        $extensionsFile = $this->rootDir.'/mate/extensions.php';
        $existingExtensions = [];
        $newPackages = [];
        $removedPackages = [];
        if (file_exists($extensionsFile)) {
            $existingExtensions = include $extensionsFile;
            if (!\is_array($existingExtensions)) {
                $existingExtensions = [];
            }
        }

        foreach ($extensions as $packageName => $data) {
            if (!isset($existingExtensions[$packageName])) {
                $newPackages[] = $packageName;
            }
        }

        foreach ($existingExtensions as $packageName => $data) {
            if (!isset($extensions[$packageName])) {
                $removedPackages[] = $packageName;
            }
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

        $finalExtensions = [];
        foreach ($extensions as $packageName => $data) {
            $enabled = true;
            if (isset($existingExtensions[$packageName]) && \is_array($existingExtensions[$packageName])) {
                $enabled = $existingExtensions[$packageName]['enabled'] ?? true;
                if (!\is_bool($enabled)) {
                    $enabled = true;
                }
            }

            $finalExtensions[$packageName] = [
                'enabled' => $enabled,
            ];
        }

        $this->writeExtensionsFile($extensionsFile, $finalExtensions);

        $io->success(\sprintf('Configuration written to: %s', $extensionsFile));

        if (\count($newPackages) > 0) {
            $io->note(\sprintf('Added %d new extension%s. All extensions are enabled by default.', \count($newPackages), 1 === \count($newPackages) ? '' : 's'));
        }

        if (\count($removedPackages) > 0) {
            $io->warning([
                \sprintf('Removed %d extension%s no longer found:', \count($removedPackages), 1 === \count($removedPackages) ? '' : 's'),
                ...array_map(fn ($pkg) => '  • '.$pkg, $removedPackages),
            ]);
        }

        $io->comment([
            'Next steps:',
            '  • Edit mate/extensions.php to enable/disable specific extensions',
            '  • Run "vendor/bin/mate serve" to start the MCP server',
        ]);

        return Command::SUCCESS;
    }

    /**
     * @param array<string, array{enabled: bool}> $extensions
     */
    private function writeExtensionsFile(string $filePath, array $extensions): void
    {
        $dir = \dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $content = "<?php\n\n";
        $content .= "// This file is managed by 'mate discover'\n";
        $content .= "// You can manually edit to enable/disable extensions\n\n";
        $content .= "return [\n";

        foreach ($extensions as $packageName => $config) {
            $enabled = $config['enabled'] ? 'true' : 'false';
            $content .= "    '$packageName' => ['enabled' => $enabled],\n";
        }

        $content .= "];\n";

        file_put_contents($filePath, $content);
    }
}
