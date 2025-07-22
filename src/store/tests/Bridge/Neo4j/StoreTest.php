<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bridge\Neo4j;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Bridge\Neo4J\Store;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

#[CoversClass(Store::class)]
final class StoreTest extends TestCase
{
    public function testStoreCannotInitializeOnInvalidResponse(): void
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([], [
                'http_code' => 400,
            ]),
        ], 'http://localhost:7474');

        $store = new Store($httpClient, 'http://localhost:7474', 'symfony', 'symfony', 'symfony', 'symfony', 'symfony');

        self::expectException(ClientException::class);
        self::expectExceptionMessage('HTTP 400 returned for "http://localhost:7474/db/symfony/query/v2".');
        self::expectExceptionCode(400);
        $store->initialize();
    }
}
