<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat\Bridge\SurrealDb\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Chat\Bridge\SurrealDb\MessageStore;
use Symfony\AI\Chat\Exception\InvalidArgumentException;
use Symfony\AI\Chat\MessageNormalizer;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Serializer;

final class MessageStoreTest extends TestCase
{
    public function testStoreCannotSetupWithExtraOptions()
    {
        $store = new MessageStore(new MockHttpClient(), 'http://127.0.0.1:8000', 'test', 'test', 'test', 'test');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No supported options.');
        $this->expectExceptionCode(0);
        $store->setup([
            'foo' => 'bar',
        ]);
    }

    public function testStoreCannotDropOnInvalidResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'code' => 200,
                'details' => 'Authentication succeeded.',
                'token' => 'bar',
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([], [
                'http_code' => 400,
            ]),
        ], 'http://127.0.0.1:8000');

        $store = new MessageStore($httpClient, 'http://127.0.0.1:8000', 'test', 'test', 'test', 'test', table: 'test');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:8000/key/test".');
        $this->expectExceptionCode(400);
        $store->drop();
    }

    public function testStoreCanDrop()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'code' => 200,
                'details' => 'Authentication succeeded.',
                'token' => 'bar',
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([
                [
                    'result' => [],
                    'status' => 'OK',
                    'time' => '151.542µs',
                ],
            ], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:8000');

        $store = new MessageStore($httpClient, 'http://127.0.0.1:8000', 'test', 'test', 'test', 'test');

        $store->setup();
        $store->drop();

        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testStoreCannotSaveOnInvalidResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'code' => 200,
                'details' => 'Authentication succeeded.',
                'token' => 'bar',
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([], [
                'http_code' => 400,
            ]),
        ], 'http://127.0.0.1:8000');

        $store = new MessageStore($httpClient, 'http://127.0.0.1:8000', 'test', 'test', 'test', 'test', table: 'test');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:8000/key/test".');
        $this->expectExceptionCode(400);
        $store->save(new MessageBag(Message::ofUser('Hello world')));
    }

    public function testStoreCanSave()
    {
        $serializer = new Serializer([
            new ArrayDenormalizer(),
            new MessageNormalizer(),
        ], [new JsonEncoder()]);

        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'code' => 200,
                'details' => 'Authentication succeeded.',
                'token' => 'bar',
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([
                [
                    'result' => [
                        $serializer->normalize(Message::ofUser('Hello world')),
                    ],
                    'status' => 'OK',
                    'time' => '263.208µs',
                ],
            ], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:8000');

        $store = new MessageStore($httpClient, 'http://127.0.0.1:8000', 'test', 'test', 'test', 'test', table: 'test');

        $store->save(new MessageBag(Message::ofUser('Hello world')));

        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testStoreCannotLoadOnInvalidResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'code' => 200,
                'details' => 'Authentication succeeded.',
                'token' => 'bar',
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([], [
                'http_code' => 400,
            ]),
        ], 'http://127.0.0.1:8000');

        $store = new MessageStore($httpClient, 'http://127.0.0.1:8000', 'test', 'test', 'test', 'test', table: 'test');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:8000/key/test".');
        $this->expectExceptionCode(400);
        $store->load();
    }

    public function testStoreCanLoad()
    {
        $serializer = new Serializer([
            new ArrayDenormalizer(),
            new MessageNormalizer(),
        ], [new JsonEncoder()]);

        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'code' => 200,
                'details' => 'Authentication succeeded.',
                'token' => 'bar',
            ], [
                'http_code' => 200,
            ]),
            new JsonMockResponse([
                [
                    'result' => [
                        $serializer->normalize(Message::ofUser('Hello World')),
                    ],
                    'status' => 'OK',
                    'time' => '263.208µs',
                ],
            ], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:8000');

        $store = new MessageStore($httpClient, 'http://127.0.0.1:8000', 'test', 'test', 'test', 'test', table: 'test');

        $messages = $store->load();

        $this->assertSame(2, $httpClient->getRequestsCount());
        $this->assertCount(1, $messages);

        $message = $messages->getUserMessage();
        $this->assertSame('Hello World', $message->asText());
    }
}
