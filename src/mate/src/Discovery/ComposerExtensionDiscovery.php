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
 * Discovers MCP extensions via extra.ai-mate config in composer.json.
 *
 * Extensions must declare themselves in composer.json:
 * {
 *   "extra": {
 *     "ai-mate": {
 *       "scan-dirs": ["src"],
 *       "includes": ["config/config.php"],
 *       "instructions": "INSTRUCTIONS.md"
 *     }
 *   }
 * }
 *
 * @phpstan-type ExtensionData array{
 *     dirs: string[],
 *     includes: string[],
 *     instructions?: string,
 * }
 *
 * @author Johannes Wachter <johannes@sulu.io>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class ComposerExtensionDiscovery
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
     * @param string[]|false $includeFilter Only include packages in this list. If false, include all.
     *
     * @return array<string, ExtensionData>
     */
    public function discover(array|false $includeFilter = false): array
    {
        $installed = $this->getInstalledPackages();
        $extensions = [];

        foreach ($installed as $package) {
            $packageName = $package['name'];

            $aiMateConfig = $package['extra']['ai-mate'] ?? null;
            if (!\is_array($aiMateConfig)) {
                continue;
            }

            // Skip if explicitly marked as non-extension
            if (isset($aiMateConfig['extension']) && false === $aiMateConfig['extension']) {
                continue;
            }

            if (false !== $includeFilter && !\in_array($packageName, $includeFilter, true)) {
                continue;
            }

            $scanDirs = $this->extractScanDirs($package, $packageName);
            $includeFiles = $this->extractIncludeFiles($package, $packageName);
            $agentInstructions = $this->extractInstructions($package, $packageName);

            if ([] !== $scanDirs || [] !== $includeFiles || null !== $agentInstructions) {
                $extensionData = [
                    'dirs' => $scanDirs,
                    'includes' => $includeFiles,
                ];

                if (null !== $agentInstructions) {
                    $extensionData['instructions'] = $agentInstructions;
                }

                $extensions[$packageName] = $extensionData;
            }
        }

        return $extensions;
    }

    /**
     * @return ExtensionData
     */
    public function discoverRootProject(): array
    {
        $composerPath = $this->rootDir.'/composer.json';
        if (!file_exists($composerPath)) {
            return [
                'dirs' => [],
                'includes' => [],
            ];
        }

        $composerContent = file_get_contents($composerPath);
        if (false === $composerContent) {
            $this->logger->warning('Failed to read root composer.json', [
                'path' => $composerPath,
                'error' => error_get_last()['message'] ?? 'Unknown error',
            ]);

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

        $result = [
            'dirs' => array_values($this->extractAiMateConfig($rootComposer, 'scan-dirs')),
            'includes' => array_values($this->extractAiMateConfig($rootComposer, 'includes')),
        ];

        $agentInstructions = $this->extractAiMateConfigString($rootComposer, 'instructions');
        if (null !== $agentInstructions) {
            $result['instructions'] = $agentInstructions;
        }

        return $result;
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
            $this->logger->warning('Could not read installed.json', [
                'path' => $installedJsonPath,
                'error' => error_get_last()['message'] ?? 'Unknown error',
            ]);

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
     * @return string[]
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

    /**
     * Extract instructions path from package extra config.
     *
     * Uses "instructions" from extra.ai-mate config, e.g.:
     * "extra": { "ai-mate": { "instructions": "INSTRUCTIONS.md" } }
     *
     * @param array{
     *     name: string,
     *     extra: array<string, mixed>,
     * } $package
     */
    private function extractInstructions(array $package, string $packageName): ?string
    {
        $aiMateConfig = $package['extra']['ai-mate'] ?? null;
        if (null === $aiMateConfig || !\is_array($aiMateConfig)) {
            return null;
        }

        $agentInstructions = $aiMateConfig['instructions'] ?? null;

        if (!\is_string($agentInstructions) || '' === trim($agentInstructions)) {
            return null;
        }

        // Security: prevent path traversal
        if (str_contains($agentInstructions, '..')) {
            $this->logger->warning('Invalid instructions path (contains "..")', [
                'package' => $packageName,
                'path' => $agentInstructions,
            ]);

            return null;
        }

        // Validate file exists
        $fullPath = $this->rootDir.'/vendor/'.$packageName.'/'.ltrim($agentInstructions, '/');
        if (!file_exists($fullPath)) {
            $this->logger->warning('Agent instructions file does not exist', [
                'package' => $packageName,
                'file' => $fullPath,
            ]);

            return null;
        }

        return $agentInstructions;
    }

    /**
     * @param array<string, mixed> $composer
     *
     * @return string[]
     */
    private function extractAiMateConfig(array $composer, string $key): array
    {
        if (!isset($composer['extra']['ai-mate'][$key])) {
            return [];
        }

        $value = $composer['extra']['ai-mate'][$key];
        if (!\is_array($value)) {
            return [];
        }

        return array_filter($value, 'is_string');
    }

    /**
     * @param array<string, mixed> $composer
     */
    private function extractAiMateConfigString(array $composer, string $key): ?string
    {
        if (!isset($composer['extra']['ai-mate'][$key])) {
            return null;
        }

        $value = $composer['extra']['ai-mate'][$key];
        if (!\is_string($value) || '' === trim($value)) {
            return null;
        }

        return $value;
    }
}
