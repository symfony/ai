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
 * Lists the packages affected by a set of changed files.
 *
 * A package is affected when it changed, when code it uses is affected (transitively, e.g. a change
 * in platform affects agent, store, all bridges, ai-bundle, demo and examples), or when its
 * tests use an affected package. Test-only usages are not followed any further.
 *
 * Dependencies are taken from composer.json and from the code itself: bridges live inside their
 * component's autoload root, so a package can use a bridge it never declared.
 *
 * Changes outside of any package (CI config, shared fixtures, root composer.json, ...) affect everything.
 *
 * usage: git diff --name-only <base>...HEAD | php .github/affected-packages.php
 *        php .github/affected-packages.php --all
 */

// Changes to these paths never affect code
const IRRELEVANT = [
    '#(^|/)(README|CHANGELOG|UPGRADE|AGENTS|CONTRIBUTING)\.md$#',
    '#^docs/#',
    '#^ai\.symfony\.com/#',
    '#^\.github/ISSUE_TEMPLATE/#',
    '#^\.github/PULL_REQUEST_TEMPLATE\.md$#',
    '#^\.github/CODEOWNERS$#',
    '#^LICENSE$#',
    '#^logo\.svg$#',
];

/**
 * @return array{
 *     packages: array<string, array{dir: string, require: list<string>, require-dev: list<string>, autoload-dev: list<string>}>,
 *     namespaces: array<string, string>,
 * } packages by name, and the directory of each autoloaded namespace
 */
function findPackages(string $root): array
{
    $composerFiles = array_merge(
        glob($root.'/src/*/composer.json'),
        glob($root.'/src/*/src/Bridge/*/composer.json'),
        [$root.'/src/mate/composer-plugin/composer.json', $root.'/demo/composer.json', $root.'/examples/composer.json'],
    );

    $packages = [];
    $namespaces = [];
    foreach ($composerFiles as $composerFile) {
        $data = json_decode(file_get_contents($composerFile), true, flags: \JSON_THROW_ON_ERROR);
        $dir = dirname($composerFile);
        $package = [
            'dir' => relativePath($root, $dir),
            'require' => array_keys($data['require'] ?? []),
            'require-dev' => array_keys($data['require-dev'] ?? []),
            'autoload-dev' => [],
        ];

        foreach (['autoload', 'autoload-dev'] as $section) {
            foreach ($data[$section]['psr-4'] ?? [] as $namespace => $paths) {
                foreach ((array) $paths as $path) {
                    if (false === $path = realpath($dir.'/'.$path)) {
                        continue;
                    }

                    $namespaces[$namespace] = relativePath($root, $path);
                    if ('autoload-dev' === $section) {
                        $package['autoload-dev'][] = $namespaces[$namespace];
                    }
                }
            }
        }

        if (!isset($data['name'])) {
            fwrite(\STDERR, sprintf("%s has no package name\n", $composerFile));
            exit(1);
        }

        $packages[$data['name']] = $package;
    }

    return ['packages' => $packages, 'namespaces' => $namespaces];
}

/**
 * @param array<string, string> $packageDirs package names by directory, deepest first
 */
function ownerOf(string $path, array $packageDirs): ?string
{
    foreach ($packageDirs as $dir => $name) {
        if (str_starts_with($path.'/', $dir.'/')) {
            return $name;
        }
    }

    return null;
}

/**
 * @return iterable<string> paths relative to the directory of the files that can refer to classes
 */
function sourceFiles(string $dir): iterable
{
    $files = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        static fn (SplFileInfo $file) => !in_array($file->getFilename(), ['vendor', 'var', 'node_modules'], true),
    ));

    foreach ($files as $file) {
        if (in_array($file->getExtension(), ['php', 'yaml', 'xml', 'twig'], true)) {
            yield relativePath($dir, $file->getPathname());
        }
    }
}

/**
 * @return list<string> the Symfony AI classes and namespaces the file refers to, outside of comments and strings
 */
function referencedClasses(string $file): array
{
    $content = file_get_contents($file);
    if (str_ends_with($file, '.php')) {
        $code = '';
        foreach (token_get_all($content) as $token) {
            if (!is_array($token)) {
                $code .= $token;
            } elseif (!in_array($token[0], [\T_COMMENT, \T_DOC_COMMENT, \T_CONSTANT_ENCAPSED_STRING, \T_ENCAPSED_AND_WHITESPACE], true)) {
                $code .= $token[1];
            }
        }
        $content = $code;
    }

    preg_match_all('/Symfony\\\\{1,2}(?:AI|McpBundle)\\\\{1,2}[\w\\\\]+/', $content, $matches);

    return array_values(array_unique(array_map(static fn ($class) => str_replace('\\\\', '\\', $class).'\\', $matches[0])));
}

/**
 * Splits the dependencies of each package into those of its shipped code and those of its tests and maintenance scripts.
 *
 * @param array<string, array{dir: string, require: list<string>, require-dev: list<string>, autoload-dev: list<string>}> $packages
 * @param array<string, string>                                                                                           $packageDirs     package names by directory, deepest first
 * @param array<string, string>                                                                                           $namespaceOwners package names by namespace, longest first
 *
 * @return array{uses: array<string, array<string, true>>, testUses: array<string, array<string, true>>}
 */
function collectUsages(string $root, array $packages, array $packageDirs, array $namespaceOwners): array
{
    $uses = [];
    $testUses = [];
    foreach ($packages as $name => $package) {
        $uses[$name] = array_fill_keys(array_intersect($package['require'], array_keys($packages)), true);
        $testUses[$name] = array_fill_keys(array_intersect($package['require-dev'], array_keys($packages)), true);

        foreach ($package['autoload-dev'] as $path) {
            if (null !== $owner = ownerOf($path, $packageDirs)) {
                $testUses[$name][$owner] = true;
            }
        }

        foreach (sourceFiles($root.'/'.$package['dir']) as $file) {
            // Nested packages, like the bridges of a component, are scanned on their own
            if ($name !== ownerOf($package['dir'].'/'.$file, $packageDirs)) {
                continue;
            }

            $isTest = (bool) preg_match('#(^|/)[Tt]ests/|^dev/#', $file);
            foreach (referencedClasses($root.'/'.$package['dir'].'/'.$file) as $class) {
                foreach ($namespaceOwners as $namespace => $owner) {
                    if (str_starts_with($class, $namespace)) {
                        if ($isTest) {
                            $testUses[$name][$owner] = true;
                        } else {
                            $uses[$name][$owner] = true;
                        }
                        break;
                    }
                }
            }
        }

        unset($uses[$name][$name], $testUses[$name][$name]);
    }

    return ['uses' => $uses, 'testUses' => $testUses];
}

/**
 * @param list<string>                       $changed  names of the changed packages
 * @param array<string, array<string, true>> $uses
 * @param array<string, array<string, true>> $testUses
 *
 * @return list<string> names of the changed packages, all their transitive code dependents, and the packages whose tests use any of them
 */
function affectedBy(array $changed, array $uses, array $testUses): array
{
    $dependents = invert($uses);
    $testDependents = invert($testUses);

    $affected = [];
    $queue = $changed;
    while ([] !== $queue) {
        $name = array_shift($queue);
        if (isset($affected[$name])) {
            continue;
        }

        $affected[$name] = true;
        array_push($queue, ...$dependents[$name] ?? []);
    }

    foreach (array_keys($affected) as $name) {
        $affected += array_fill_keys($testDependents[$name] ?? [], true);
    }

    return array_keys($affected);
}

/**
 * @param array<string, array<string, true>> $uses
 *
 * @return array<string, list<string>> the packages using each package
 */
function invert(array $uses): array
{
    $dependents = [];
    foreach ($uses as $name => $dependencies) {
        foreach (array_keys($dependencies) as $dependency) {
            $dependents[$dependency][] = $name;
        }
    }

    return $dependents;
}

/**
 * @param array<string, string> $packageDirs package names by directory, deepest first
 *
 * @return list<string>|null names of the packages containing the files, or null when a file is outside of any package
 */
function changedPackages(array $files, array $packageDirs): ?array
{
    $changed = [];
    foreach ($files as $file) {
        foreach (IRRELEVANT as $pattern) {
            if (preg_match($pattern, $file)) {
                continue 2;
            }
        }

        if (null === $owner = ownerOf($file, $packageDirs)) {
            fwrite(\STDERR, sprintf("%s is not part of a package, everything is affected\n", $file));

            return null;
        }

        $changed[$owner] = true;
    }

    return array_keys($changed);
}

function relativePath(string $root, string $path): string
{
    return substr($path, strlen($root) + 1);
}

$root = dirname(__DIR__);
['packages' => $packages, 'namespaces' => $namespaces] = findPackages($root);

$packageDirs = array_column(array_map(null, array_column($packages, 'dir'), array_keys($packages)), 1, 0);
uksort($packageDirs, static fn ($a, $b) => strlen($b) <=> strlen($a));

// Namespaces may point into another package's directory, so they are owned by path
$namespaceOwners = array_filter(array_map(static fn ($dir) => ownerOf($dir, $packageDirs), $namespaces));
uksort($namespaceOwners, static fn ($a, $b) => strlen($b) <=> strlen($a));

$changed = in_array('--all', $argv, true) ? null : changedPackages(array_filter(array_map('trim', file('php://stdin'))), $packageDirs);
if (null === $changed) {
    $affected = array_keys($packages);
} else {
    ['uses' => $uses, 'testUses' => $testUses] = collectUsages($root, $packages, $packageDirs, $namespaceOwners);
    $affected = affectedBy($changed, $uses, $testUses);
}

$dirs = array_map(static fn ($name) => $packages[$name]['dir'], $affected);
sort($dirs);

echo implode("\n", $dirs).([] === $dirs ? '' : "\n");
