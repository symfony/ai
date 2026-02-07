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
use Symfony\Component\HttpClient\MockHttpClient;

final class StoreTest extends TestCase
{
    public function testStoreCannotSetupWithOptions()
    {
        $store = new Store(new MockHttpClient(), 'foo', 'http://127.0.0.1:8080');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No supported options.');
        $this->expectExceptionCode(0);
        $store->setup([
            'foo' => 'bar',
        ]);
    }
}
