<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require dirname(__DIR__, 3).'/vendor/autoload.php';

spl_autoload_register(static function (string $class): void {
    foreach (['Symfony\\AI\\Bc\\Tests\\' => __DIR__.'/', 'Symfony\\AI\\Bc\\' => dirname(__DIR__).'/src/'] as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            require $dir.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

            return;
        }
    }
});
