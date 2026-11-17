<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Bc;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Package
{
    /**
     * @param string       $dir         relative to the repository root
     * @param list<string> $sourceFiles relative to the repository root
     */
    public function __construct(
        public readonly string $name,
        public readonly string $dir,
        public readonly array $sourceFiles,
    ) {
    }

    public function snapshotFile(): string
    {
        return '.github/bc/api/'.str_replace('symfony/', '', $this->name).'.txt';
    }

    /**
     * @param string $root the directory holding "src/", e.g. the repository or an extracted git tree
     *
     * @return list<self>
     */
    public static function findAll(string $root): array
    {
        $composerFiles = array_merge(
            glob($root.'/src/*/composer.json') ?: [],
            glob($root.'/src/*/src/Bridge/*/composer.json') ?: [],
            glob($root.'/src/mate/composer-plugin/composer.json') ?: [],
        );

        $packageDirs = array_map(static fn (string $file): string => \dirname($file), $composerFiles);

        $packages = [];
        foreach ($composerFiles as $composerFile) {
            $data = json_decode((string) file_get_contents($composerFile), true, flags: \JSON_THROW_ON_ERROR);
            $dir = \dirname($composerFile);

            $files = [];
            foreach ($data['autoload']['psr-4'] ?? [] as $paths) {
                foreach ((array) $paths as $path) {
                    if (false === $path = realpath($dir.'/'.$path)) {
                        continue;
                    }
                    array_push($files, ...self::sourceFiles($path, $dir, $packageDirs, $root));
                }
            }

            $files = array_values(array_unique($files));
            sort($files);

            $packages[] = new self($data['name'], substr($dir, \strlen($root) + 1), $files);
        }

        usort($packages, static fn (self $a, self $b): int => strcmp($a->dir, $b->dir));

        return $packages;
    }

    /**
     * @param list<string> $packageDirs
     *
     * @return list<string>
     */
    private static function sourceFiles(string $path, string $packageDir, array $packageDirs, string $root): array
    {
        $realPackageDirs = array_map(static fn (string $dir): string => (string) realpath($dir), $packageDirs);
        $realPackageDir = (string) realpath($packageDir);
        $realRoot = (string) realpath($root);

        $iterator = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            static function (\SplFileInfo $file) use ($realPackageDirs, $realPackageDir): bool {
                if (!$file->isDir()) {
                    return 'php' === $file->getExtension();
                }
                if (\in_array($file->getFilename(), ['Tests', 'tests', 'vendor', 'var', 'node_modules'], true)) {
                    return false;
                }

                $real = $file->getRealPath();

                return $real === $realPackageDir || !\in_array($real, $realPackageDirs, true);
            },
        ));

        $files = [];
        foreach ($iterator as $file) {
            $files[] = substr((string) $file->getRealPath(), \strlen($realRoot) + 1);
        }

        return $files;
    }
}
