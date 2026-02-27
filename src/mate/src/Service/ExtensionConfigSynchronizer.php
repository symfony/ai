<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Service;

use Symfony\AI\Mate\Discovery\ComposerExtensionDiscovery;

/**
 * Synchronizes discovered extensions with mate/extensions.php while preserving enabled flags.
 *
 * @phpstan-import-type ExtensionData from ComposerExtensionDiscovery
 *
 * @phpstan-type ExtensionConfig array{enabled: bool}
 * @phpstan-type ExtensionConfigMap array<string, ExtensionConfig>
 * @phpstan-type SynchronizationResult array{
 *     extensions: ExtensionConfigMap,
 *     new_packages: string[],
 *     removed_packages: string[],
 *     file: string,
 * }
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ExtensionConfigSynchronizer
{
    public function __construct(
        private string $rootDir,
    ) {
    }

    /**
     * @param array<string, ExtensionData> $discoveredExtensions
     *
     * @return SynchronizationResult
     */
    public function synchronize(array $discoveredExtensions): array
    {
        $extensionsFile = $this->rootDir.'/mate/extensions.php';
        $existingExtensions = $this->readExistingExtensions($extensionsFile);

        $newPackages = [];
        foreach (array_keys($discoveredExtensions) as $packageName) {
            if (!isset($existingExtensions[$packageName])) {
                $newPackages[] = $packageName;
            }
        }

        $removedPackages = [];
        foreach (array_keys($existingExtensions) as $packageName) {
            if (!isset($discoveredExtensions[$packageName])) {
                $removedPackages[] = $packageName;
            }
        }

        $finalExtensions = [];
        foreach (array_keys($discoveredExtensions) as $packageName) {
            $enabled = true;
            if (isset($existingExtensions[$packageName]) && \is_array($existingExtensions[$packageName])) {
                $enabledValue = $existingExtensions[$packageName]['enabled'] ?? true;
                if (\is_bool($enabledValue)) {
                    $enabled = $enabledValue;
                }
            }

            $finalExtensions[$packageName] = [
                'enabled' => $enabled,
            ];
        }

        $this->writeExtensionsFile($extensionsFile, $finalExtensions);

        return [
            'extensions' => $finalExtensions,
            'new_packages' => $newPackages,
            'removed_packages' => $removedPackages,
            'file' => $extensionsFile,
        ];
    }

    /**
     * @return array<string, array{enabled?: bool}>
     */
    private function readExistingExtensions(string $extensionsFile): array
    {
        if (!file_exists($extensionsFile)) {
            return [];
        }

        $existingExtensions = include $extensionsFile;
        if (!\is_array($existingExtensions)) {
            return [];
        }

        /* @var array<string, array{enabled?: bool}> $existingExtensions */
        return $existingExtensions;
    }

    /**
     * @param ExtensionConfigMap $extensions
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
