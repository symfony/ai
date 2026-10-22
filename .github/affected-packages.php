<?php

/**
 * Lists the packages affected by a set of changed files.
 *
 * A package is affected when it changed, when code it uses is affected (transitively, e.g. a change
 * in platform affects agent, chat, store, all bridges, ai-bundle, demo and examples), or when its
 * tests use an affected package. Test-only usages are not followed any further.
 *
 * Dependencies are taken from composer.json and from the code itself: bridges live inside their
 * component's autoload root, so a package can use a bridge it never declared.
 *
 * Changes outside of any package (CI config, shared fixtures, root composer.json, ...) affect everything.
 *
 * usage: git diff --name-only <base>...HEAD | php .github/affected-packages.php
 */

$root = dirname(__DIR__);

// Changes to these paths never affect code
$irrelevant = ['#\.md$#', '#\.rst$#', '#^docs/#', '#^ai\.symfony\.com/#', '#^\.github/ISSUE_TEMPLATE/#', '#^\.github/PULL_REQUEST_TEMPLATE\.md$#', '#^\.github/CODEOWNERS$#', '#^LICENSE$#', '#^logo\.svg$#'];

// 1. Find all packages
$packages = [];
$namespaces = [];
$composerFiles = array_merge(
    glob($root.'/src/*/composer.json'),
    glob($root.'/src/*/src/Bridge/*/composer.json'),
    glob($root.'/src/mate/composer-plugin/composer.json'),
    [$root.'/demo/composer.json', $root.'/examples/composer.json'],
);
foreach ($composerFiles as $composerFile) {
    $data = json_decode(file_get_contents($composerFile), true, flags: \JSON_THROW_ON_ERROR);
    $dir = dirname($composerFile);
    $packages[$data['name']] = [
        'dir' => substr($dir, strlen($root) + 1),
        'require' => array_keys($data['require'] ?? []),
        'require-dev' => array_keys($data['require-dev'] ?? []),
        'autoload-dev' => [],
    ];
    foreach (['autoload', 'autoload-dev'] as $section) {
        foreach ($data[$section]['psr-4'] ?? [] as $namespace => $paths) {
            foreach ((array) $paths as $path) {
                $path = realpath($dir.'/'.$path);
                if (false === $path) {
                    continue;
                }
                $namespaces[$namespace] = substr($path, strlen($root) + 1);
                if ('autoload-dev' === $section) {
                    $packages[$data['name']]['autoload-dev'][] = $namespaces[$namespace];
                }
            }
        }
    }
}

// Deepest directories first, so a bridge wins over its component
$byDir = array_column(array_map(static fn ($name, $package) => [$package['dir'].'/', $name], array_keys($packages), $packages), 1, 0);
uksort($byDir, static fn ($a, $b) => strlen($b) <=> strlen($a));
$ownerOf = static function (string $path) use ($byDir): ?string {
    foreach ($byDir as $dir => $name) {
        if (str_starts_with($path.'/', $dir)) {
            return $name;
        }
    }

    return null;
};

// Namespaces may point into another package's directory, so they are owned by path
$namespaceOwners = array_filter(array_map($ownerOf, $namespaces));
uksort($namespaceOwners, static fn ($a, $b) => strlen($b) <=> strlen($a));

// 2. Collect the dependencies of each package, split into code and test-only usages
$uses = [];
$testUses = [];
foreach ($packages as $name => $package) {
    $uses[$name] = array_fill_keys(array_intersect($package['require'], array_keys($packages)), true);
    $testUses[$name] = array_fill_keys(array_intersect($package['require-dev'], array_keys($packages)), true);

    foreach ($package['autoload-dev'] as $path) {
        if (null !== $owner = $ownerOf($path)) {
            $testUses[$name][$owner] = true;
        }
    }

    $files = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root.'/'.$package['dir'], FilesystemIterator::SKIP_DOTS),
        static fn (SplFileInfo $file) => !in_array($file->getFilename(), ['vendor', 'var', 'node_modules'], true),
    ));
    foreach ($files as $file) {
        $path = substr($file->getPathname(), strlen($root) + 1);
        if (!in_array($file->getExtension(), ['php', 'yaml', 'xml', 'twig'], true) || $name !== $ownerOf(dirname($path))) {
            continue;
        }

        // Tests and maintenance scripts are not shipped, nothing depending on the package uses them
        $isTest = (bool) preg_match('#^/([Tt]ests|dev)/|/[Tt]ests/#', substr($path, strlen($package['dir'])));
        $content = file_get_contents($file->getPathname());
        if ('php' === $file->getExtension()) {
            $content = implode('', array_map(static fn ($token) => is_array($token) ? (in_array($token[0], [\T_COMMENT, \T_DOC_COMMENT], true) ? '' : $token[1]) : $token, token_get_all($content)));
        }
        preg_match_all('/Symfony\\\\{1,2}(?:AI|McpBundle)\\\\{1,2}[\w\\\\]+/', $content, $matches);
        foreach (array_unique($matches[0]) as $class) {
            $class = str_replace('\\\\', '\\', $class).'\\';
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
}

foreach ($packages as $name => $package) {
    unset($uses[$name][$name], $testUses[$name][$name]);
}

// 3. Invert the graph: package => packages using it
$dependents = array_fill_keys(array_keys($packages), []);
$testDependents = array_fill_keys(array_keys($packages), []);
foreach ($packages as $name => $package) {
    foreach (array_keys($uses[$name]) as $dependency) {
        $dependents[$dependency][] = $name;
    }
    foreach (array_keys($testUses[$name]) as $dependency) {
        $testDependents[$dependency][] = $name;
    }
}

// 4. Map changed files to packages
$all = false;
$changed = [];
while (false !== $file = fgets(\STDIN)) {
    $file = trim($file);
    if ('' === $file) {
        continue;
    }

    foreach ($irrelevant as $pattern) {
        if (preg_match($pattern, $file)) {
            continue 2;
        }
    }

    if (null !== $owner = $ownerOf($file)) {
        $changed[$owner] = true;
        continue;
    }

    fwrite(\STDERR, sprintf("%s is not part of a package, everything is affected\n", $file));
    $all = true;
}

// 5. Expand to all transitive code dependents, then to the packages whose tests use any of them
$affected = $all ? array_fill_keys(array_keys($packages), true) : [];
$queue = array_keys($changed);
while (!$all && [] !== $queue) {
    $name = array_shift($queue);
    if (isset($affected[$name])) {
        continue;
    }
    $affected[$name] = true;
    array_push($queue, ...$dependents[$name]);
}
foreach (array_keys($affected) as $name) {
    $affected += array_fill_keys($testDependents[$name], true);
}

$dirs = array_map(static fn ($name) => $packages[$name]['dir'], array_keys($affected));
sort($dirs);

echo implode("\n", $dirs).([] === $dirs ? '' : "\n");
