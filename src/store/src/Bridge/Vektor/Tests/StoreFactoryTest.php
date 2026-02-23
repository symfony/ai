<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Vektor\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Bridge\Vektor\Store;
use Symfony\AI\Store\Bridge\Vektor\StoreFactory;
use Symfony\Component\Filesystem\Filesystem;

final class StoreFactoryTest extends TestCase
{
    public function testStoreCanBeCreated()
    {
        $store = StoreFactory::create(sys_get_temp_dir(), 3, new Filesystem());

        $this->assertInstanceOf(Store::class, $store);
    }
}
