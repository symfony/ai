<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Vektor;

use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class StoreFactory
{
    public static function create(
        string $storagePath,
        int $dimensions = 1536,
        Filesystem $filesystem = new Filesystem(),
    ): StoreInterface&ManagedStoreInterface {
        return new Store($storagePath, $dimensions, $filesystem);
    }
}
