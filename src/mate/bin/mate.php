<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$autoloadPaths = [
    getcwd().'/vendor/autoload.php',   // Project autoloader using current-working-directory (preferred)
    __DIR__.'/../../../autoload.php',  // Project autoloader
    __DIR__.'/../vendor/autoload.php', // Package autoloader (fallback)
];

if (isset($GLOBALS['_composer_autoload_path'])) {
    array_unshift($autoloadPaths, $GLOBALS['_composer_autoload_path']);
}

$root = null;
foreach ($autoloadPaths as $autoloadPath) {
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
        $root = dirname(realpath($autoloadPath), 2);
        break;
    }
}

if (!$root) {
    echo 'Unable to locate the Composer vendor directory. Did you run composer install?'.\PHP_EOL;
    exit(1);
}

use Symfony\AI\Mate\App;
use Symfony\AI\Mate\Container\ContainerFactory;
use Symfony\AI\Mate\Exception\ContainerCompilationException;
use Symfony\AI\Mate\Exception\PhpVersionMismatchException;

// The first bare argument is the command name; the guard needs it to stay out of `init`'s way.
$commandName = null;
foreach (array_slice($_SERVER['argv'] ?? [], 1) as $argument) {
    if (!str_starts_with($argument, '-')) {
        $commandName = $argument;
        break;
    }
}

try {
    $containerFactory = new ContainerFactory($root);
    $container = $containerFactory->create();
    $application = App::build($container, $commandName);
} catch (ContainerCompilationException|PhpVersionMismatchException $e) {
    // Thrown before the console exists, so report it here rather than as a stack trace.
    fwrite(\STDERR, \PHP_EOL.' [ERROR] '.$e->getMessage().\PHP_EOL.\PHP_EOL);

    exit(1);
}

$application->run();
