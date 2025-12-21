<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Discovery;

use Psr\Log\LoggerInterface;

/**
 * Discovers MCP bridges via extra.ai-mate config in composer.json.
 *
 * Bridges must declare themselves in composer.json:
 * {
 *   "extra": {
 *     "ai-mate": {
 *       "scan-dirs": ["src"],
 *       "includes": ["config/config.php"]
 *     }
 *   }
 * }
 *
 * @author Johannes Wachter <johannes@sulu.io>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class ComposerTypeDiscovery
{
    /**
     * @var array<string, array{
     *     name: string,
     *     extra: array<string, mixed>,
     * }>|null
     */
    private ?array $installedPackages = null;

    public function __construct(
        private string $rootDir,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param string[] $enabledBridges
     *
     * @return array<string, array{dirs: string[], includes: string[]}>
     */
    public function discover(array $enabledBridges = []): array
    {
        $installed = $this->getInstalledPackages();
        $bridges = [];

        foreach ($installed as $package) {
            $packageName = $package['name'];

            $aiMateConfig = $package['extra']['ai-mate'] ?? null;
            if (!\is_array($aiMateConfig)) {
                continue;
            }

            if ([] !== $enabledBridges && !\in_array($packageName, $enabledBridges, true)) {
                $this->logger->debug('Skipping package not enabled', ['package' => $packageName]);

                continue;
            }

            $scanDirs = $this->extractScanDirs($package, $packageName);
            $includeFiles = $this->extractIncludeFiles($package, $packageName);
            if ([] !== $scanDirs || [] !== $includeFiles) {
                $bridges[$packageName] = [
                    'dirs' => $scanDirs,
                    'includes' => $includeFiles,
                ];
            }
        }

        return $bridges;
    }

    /**
     * @return array{dirs: array<string>, includes: array<string>}
     */
    public function discoverRootProject(): array
    {
        $composerContent = file_get_contents($this->rootDir.'/composer.json');
        if (false === $composerContent) {
            return [
                'dirs' => [],
                'includes' => [],
            ];
        }

        $rootComposer = json_decode($composerContent, true);
        if (!\is_array($rootComposer)) {
            return [
                'dirs' => [],
                'includes' => [],
            ];
        }

        $scanDirs = [];
        if (isset($rootComposer['extra']) && \is_array($rootComposer['extra'])
            && isset($rootComposer['extra']['ai-mate']) && \is_array($rootComposer['extra']['ai-mate'])
            && isset($rootComposer['extra']['ai-mate']['scan-dirs']) && \is_array($rootComposer['extra']['ai-mate']['scan-dirs'])) {
            $scanDirs = array_filter($rootComposer['extra']['ai-mate']['scan-dirs'], 'is_string');
        }

        $includes = [];
        if (isset($rootComposer['extra']) && \is_array($rootComposer['extra'])
            && isset($rootComposer['extra']['ai-mate']) && \is_array($rootComposer['extra']['ai-mate'])
            && isset($rootComposer['extra']['ai-mate']['includes']) && \is_array($rootComposer['extra']['ai-mate']['includes'])) {
            $includes = array_filter($rootComposer['extra']['ai-mate']['includes'], 'is_string');
        }

        return [
            'dirs' => array_values($scanDirs),
            'includes' => array_values($includes),
        ];
    }

    /**
     * Check vendor/composer/installed.json for installed packages.
     *
     * @return array<string, array{
     *     name: string,
     *     extra: array<string, mixed>,
     * }>
     */
    private function getInstalledPackages(): array
    {
        if (null !== $this->installedPackages) {
            return $this->installedPackages;
        }

        $installedJsonPath = $this->rootDir.'/vendor/composer/installed.json';
        if (!file_exists($installedJsonPath)) {
            $this->logger->warning('Composer installed.json not found', ['path' => $installedJsonPath]);

            return $this->installedPackages = [];
        }

        $content = file_get_contents($installedJsonPath);
        if (false === $content) {
            $this->logger->warning('Could not read installed.json', ['path' => $installedJsonPath]);

            return $this->installedPackages = [];
        }

        try {
            $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->error('Invalid JSON in installed.json', ['error' => $e->getMessage()]);

            return $this->installedPackages = [];
        }

        if (!\is_array($data)) {
            return $this->installedPackages = [];
        }

        // Handle both formats: {"packages": [...]} and direct array
        $packages = $data['packages'] ?? $data;
        if (!\is_array($packages)) {
            return $this->installedPackages = [];
        }

        $indexed = [];
        foreach ($packages as $package) {
            if (!\is_array($package) || !isset($package['name']) || !\is_string($package['name'])) {
                continue;
            }

            /** @var array{
             *     name: string,
             *     extra: array<string, mixed>,
             * } $validPackage */
            $validPackage = [
                'name' => $package['name'],
                'extra' => [],
            ];

            if (isset($package['extra']) && \is_array($package['extra'])) {
                /** @var array<string, mixed> $extra */
                $extra = $package['extra'];
                $validPackage['extra'] = $extra;
            }

            $indexed[$package['name']] = $validPackage;
        }

        return $this->installedPackages = $indexed;
    }

    /**
     * @param array{
     *     name: string,
     *     extra: array<string, mixed>,
     * } $package
     *
     * @return string[] list of directories with paths relative to project root
     */
    private function extractScanDirs(array $package, string $packageName): array
    {
        $aiMateConfig = $package['extra']['ai-mate'] ?? null;
        if (null === $aiMateConfig) {
            // Default: scan package root directory if no config provided
            $defaultDir = 'vendor/'.$packageName;
            if (is_dir($this->rootDir.'/'.$defaultDir)) {
                return [$defaultDir];
            }

            $this->logger->warning('Package directory not found', [
                'package' => $packageName,
                'directory' => $defaultDir,
            ]);

            return [];
        }

        if (!\is_array($aiMateConfig)) {
            $this->logger->warning('Invalid ai-mate config in package', ['package' => $packageName]);

            return [];
        }

        $scanDirs = $aiMateConfig['scan-dirs'] ?? [];
        if (!\is_array($scanDirs)) {
            $this->logger->warning('Invalid scan-dirs in ai-mate config', ['package' => $packageName]);

            return [];
        }

        $validDirs = [];
        foreach ($scanDirs as $dir) {
            if (!\is_string($dir) || '' === trim($dir) || str_contains($dir, '..')) {
                continue;
            }

            $fullPath = 'vendor/'.$packageName.'/'.ltrim($dir, '/');
            if (!is_dir($this->rootDir.'/'.$fullPath)) {
                $this->logger->warning('Scan directory does not exist', [
                    'package' => $packageName,
                    'directory' => $fullPath,
                ]);
                continue;
            }

            $validDirs[] = $fullPath;
        }

        return $validDirs;
    }

    /**
     * Extract include files from package extra config.
     *
     * Uses "includes" from extra.ai-mate config, e.g.:
     * "extra": { "ai-mate": { "includes": ["config/config.php"] } }
     *
     * @param array{
     *     name: string,
     *     extra: array<string, mixed>,
     * } $package
     *
     * @return string[] list of files with paths relative to project root
     */
    private function extractIncludeFiles(array $package, string $packageName): array
    {
        $aiMateConfig = $package['extra']['ai-mate'] ?? null;
        if (null === $aiMateConfig || !\is_array($aiMateConfig)) {
            return [];
        }

        $includes = $aiMateConfig['includes'] ?? [];

        // Support single file as string
        if (\is_string($includes)) {
            $includes = [$includes];
        }

        if (!\is_array($includes)) {
            $this->logger->warning('Invalid includes in ai-mate config', ['package' => $packageName]);

            return [];
        }

        $validFiles = [];
        foreach ($includes as $file) {
            if (!\is_string($file) || '' === trim($file) || str_contains($file, '..')) {
                continue;
            }

            $fullPath = $this->rootDir.'/vendor/'.$packageName.'/'.ltrim($file, '/');
            if (!file_exists($fullPath)) {
                $this->logger->warning('Include file does not exist', [
                    'package' => $packageName,
                    'file' => $fullPath,
                ]);
                continue;
            }

            $validFiles[] = $fullPath;
        }

        return $validFiles;
    }
}
