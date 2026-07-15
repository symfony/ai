<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * Generates a merged root composer.json ("workspace") that installs every package of the monorepo,
 * plus the union of their third-party requirements, into a single vendor tree.
 *
 * Tools that only need an autoloader -- PHPStan and PHPUnit -- then run against that one install
 * instead of one composer install per package.
 *
 * Must run after .github/build-packages.php, which rewrites the inter-package requirements to
 * "@dev" and registers the path repositories.
 */

require __DIR__.'/../vendor/autoload.php';

use Symfony\Component\Finder\Finder;

$root = dirname(__DIR__);

$finder = (new Finder())
    ->in([$root.'/src/*/', $root.'/src/*/src/Bridge/*/', $root.'/src/mate/composer-plugin/'])
    ->depth(0)
    ->name('composer.json')
;

$rootJson = json_decode(file_get_contents($root.'/composer.json'), true, 512, \JSON_THROW_ON_ERROR);

$packages = [];
$thirdParty = [];
$autoloadDev = [];

foreach ($finder as $composerFile) {
    $data = json_decode(file_get_contents($composerFile->getPathname()), true, 512, \JSON_THROW_ON_ERROR);
    $packages[$data['name']] = true;
}

foreach ($finder as $composerFile) {
    $dir = $composerFile->getPath();
    $data = json_decode(file_get_contents($composerFile->getPathname()), true, 512, \JSON_THROW_ON_ERROR);

    foreach (['require', 'require-dev'] as $section) {
        foreach ($data[$section] ?? [] as $dep => $constraint) {
            if (isset($packages[$dep]) || 'php' === $dep || str_starts_with($dep, 'ext-')) {
                continue;
            }

            $thirdParty[$dep][$constraint] = true;
        }
    }

    // Test suites and PHPStan extensions live in autoload-dev, which composer ignores for
    // path dependencies. Merge them into the workspace root so test classes stay autoloadable.
    foreach ($data['autoload-dev']['psr-4'] ?? [] as $namespace => $paths) {
        foreach ((array) $paths as $path) {
            $absolute = realpath($dir.'/'.$path);
            if (false === $absolute) {
                continue;
            }

            $relative = ltrim(substr($absolute, strlen($root)), '/');
            $autoloadDev[$namespace][$relative] = true;
        }
    }
}

$requireDev = $rootJson['require-dev'] ?? [];

foreach (array_keys($packages) as $name) {
    $requireDev[$name] = '@dev';
}

foreach ($thirdParty as $dep => $constraints) {
    if (isset($requireDev[$dep])) {
        $constraints[$requireDev[$dep]] = true;
    }

    // A comma is composer's AND operator: the resolver intersects every package's constraint,
    // which is exactly what a per-package install would have picked.
    $requireDev[$dep] = implode(', ', array_keys($constraints));
}

ksort($requireDev);
ksort($autoloadDev);

$rootJson['repositories'] = [
    ['type' => 'path', 'url' => 'src/*/', 'options' => ['symlink' => true]],
    ['type' => 'path', 'url' => 'src/*/src/Bridge/*/', 'options' => ['symlink' => true]],
    ['type' => 'path', 'url' => 'src/mate/composer-plugin/', 'options' => ['symlink' => true]],
];
$rootJson['require-dev'] = $requireDev;
$rootJson['autoload-dev'] = ['psr-4' => array_map(
    static fn (array $paths) => 1 === count($paths) ? array_key_first($paths) : array_keys($paths),
    $autoloadDev,
)];

// Every package declares "minimum-stability: dev" without "prefer-stable". Keeping the root's
// "prefer-stable" would resolve different versions than a per-package install does.
unset($rootJson['prefer-stable']);

$rootJson['config']['allow-plugins'] = [
    'codewithkyrian/platform-package-installer' => true,
    'php-http/discovery' => true,
    'symfony/ai-mate-composer-plugin' => true,
];

file_put_contents(
    $root.'/composer.json',
    json_encode($rootJson, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE)."\n",
);

printf("Workspace: %d packages, %d third-party requirements, %d autoload-dev namespaces\n",
    count($packages), count($thirdParty), count($autoloadDev));
