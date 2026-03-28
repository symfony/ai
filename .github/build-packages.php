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

    foreach ($aiPackages as $packageName => $packageInfo) {
        if (isset($packageData['require'][$packageName])
            || isset($packageData['require-dev'][$packageName])
        ) {
            $repositories[] = [
                'type' => 'path',
                'url' => $packageInfo['path'],
            ];
            $key = isset($packageData['require'][$packageName]) ? 'require' : 'require-dev';
            $packageData[$key][$packageName] = '@dev';
        }
    }

    // Add the composer-plugin path repo for packages that depend on symfony/ai-mate,
    // since it transitively requires symfony/ai-mate-composer-plugin.
    if (isset($aiPackages['symfony/ai-mate-composer-plugin'])
        && (isset($packageData['require']['symfony/ai-mate']) || isset($packageData['require-dev']['symfony/ai-mate']))
    ) {
        $repositories[] = [
            'type' => 'path',
            'url' => $aiPackages['symfony/ai-mate-composer-plugin']['path'],
        ];
    }

    if ($repositories) {
        $packageData['repositories'] = $repositories;
    }

    $json = json_encode($packageData, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    file_put_contents($composerFile->getPathname(), $json."\n");
}
