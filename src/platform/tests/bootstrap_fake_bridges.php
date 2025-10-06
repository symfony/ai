<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$loader = require __DIR__.'/../../../vendor/autoload.php';

$loader->addPsr4(
    'Symfony\\AI\\Platform\\Bridge\\',
    __DIR__.'/Factory/FakeBridges',
    true // PREPEND
);
