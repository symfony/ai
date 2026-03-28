<?php

/**
 * Updates the composer.json files to use the local version of the Symfony AI packages.
 */

require __DIR__.'/../vendor/autoload.php';

use Symfony\Component\Finder\Finder;

$finder = (new Finder())
    ->in([__DIR__.'/../src/*/', __DIR__.'/../src/*/src/Bridge/*/', __DIR__.'/../src/mate/composer-plugin/', __DIR__.'/../examples/', __DIR__.'/../demo/'])
    ->depth(0)
    ->name('composer.json')
;

// 1. Find all AI packages
$aiPackages = [];
foreach ($finder as $composerFile) {
    $json = file_get_contents($composerFile->getPathname());
    if (null === $packageData = json_decode($json, true)) {
        passthru(sprintf('composer validate %s', $composerFile->getPathname()));
        exit(1);
    }

    if (str_starts_with($composerFile->getPathname(), __DIR__ . '/../src/')) {
        $packageName = $packageData['name'];

        $aiPackages[$packageName] = [
            'path' => realpath($composerFile->getPath()),
        ];
    }
}

// 2. Update all composer.json files from the repository, to use the local version of the AI packages
foreach ($finder as $composerFile) {
    $json = file_get_contents($composerFile->getPathname());
    if (null === $packageData = json_decode($json, true)) {
        passthru(sprintf('composer validate %s', $composerFile->getPathname()));
        exit(1);
    }

    $repositories = $packageData['repositories'] ?? [];
    $addedRepoPaths = [];
    $currentPackageName = $packageData['name'] ?? null;

    foreach ($aiPackages as $packageName => $packageInfo) {
        if (isset($packageData['require'][$packageName])
            || isset($packageData['require-dev'][$packageName])
        ) {
            if (!isset($addedRepoPaths[$packageInfo['path']])) {
                $repositories[] = [
                    'type' => 'path',
                    'url' => $packageInfo['path'],
                ];
                $addedRepoPaths[$packageInfo['path']] = true;
            }
            $key = isset($packageData['require'][$packageName]) ? 'require' : 'require-dev';
            $packageData[$key][$packageName] = '@dev';

            // Also register path repos for transitive AI dependencies
            // so that e.g. symfony/ai-mate -> symfony/ai-mate-composer-plugin resolves.
            $depComposerFile = $packageInfo['path'].'/composer.json';
            if (file_exists($depComposerFile)) {
                $depData = json_decode(file_get_contents($depComposerFile), true);
                if (\is_array($depData)) {
                    foreach ($aiPackages as $transName => $transInfo) {
                        if ($transName !== $currentPackageName
                            && !isset($addedRepoPaths[$transInfo['path']])
                            && (isset($depData['require'][$transName]) || isset($depData['require-dev'][$transName]))
                        ) {
                            $repositories[] = [
                                'type' => 'path',
                                'url' => $transInfo['path'],
                            ];
                            $addedRepoPaths[$transInfo['path']] = true;
                        }
                    }
                }
            }
        }
    }

    if ($repositories) {
        $packageData['repositories'] = $repositories;
    }

    $json = json_encode($packageData, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    file_put_contents($composerFile->getPathname(), $json."\n");
}
