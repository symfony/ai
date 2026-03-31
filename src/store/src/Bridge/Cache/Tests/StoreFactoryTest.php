<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Cache\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Bridge\Cache\Store;
use Symfony\AI\Store\Bridge\Cache\StoreFactory;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class StoreFactoryTest extends TestCase
{
    public function testStoreCanBeCreated()
    {
        $store = StoreFactory::create(new ArrayAdapter(), 'foo');
        $this->assertInstanceOf(Store::class, $store);
    }
}
