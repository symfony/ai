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

$containerFactory = new ContainerFactory($root);
$container = $containerFactory->create();

App::build($container)->run();
