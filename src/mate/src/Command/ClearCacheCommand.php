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

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

/**
 * Clear the MCP server cache.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
#[AsCommand('clear-cache', 'Clear the MCP server cache')]
class ClearCacheCommand extends Command
{
    public function __construct(
        private string $cacheDir,
    ) {
        parent::__construct(self::getDefaultName());
    }

    public static function getDefaultName(): string
    {
        return 'clear-cache';
    }

    public static function getDefaultDescription(): string
    {
        return 'Clear the MCP server cache';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Cache Management');
        $io->text(\sprintf('Cache directory: <info>%s</info>', $this->cacheDir));
        $io->newLine();

        $cacheDir = $this->cacheDir;

        if (!is_dir($cacheDir)) {
            $io->note('Cache directory does not exist. Nothing to clear.');

            return Command::SUCCESS;
        }

        $finder = new Finder();
        $finder->files()->in($cacheDir);

        $count = 0;
        $totalSize = 0;
        $fileList = [];

        foreach ($finder as $file) {
            $size = $file->getSize();
            $totalSize += $size;
            $fileList[] = [
                basename($file->getFilename()),
                $this->formatBytes($size),
            ];
            unlink($file->getRealPath());
            ++$count;
        }

        if ($count > 0) {
            $io->section('Cleared Files');
            $io->table(['File', 'Size'], $fileList);

            $io->success(\sprintf(
                'Successfully cleared %d cache file%s (%s)',
                $count,
                1 === $count ? '' : 's',
                $this->formatBytes($totalSize)
            ));
        } else {
            $io->info('Cache directory is already empty.');
        }

        return Command::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 2).' KB';
        }

        return round($bytes / 1048576, 2).' MB';
    }
}
