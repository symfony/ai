<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat\Bridge\Pogocache\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Chat\Bridge\Pogocache\MessageStore;
use Symfony\AI\Chat\MessageNormalizer;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Serializer;

final class MessageStoreTest extends TestCase
{
    public function testStoreCannotSetupOnInvalidResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([], [
                'http_code' => 400,
            ]),
        ], 'http://127.0.0.1:9401');

        $store = new MessageStore($httpClient, 'http://127.0.0.1:9401', 'test', 'test');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:9401/test?auth=test".');
        $this->expectExceptionCode(400);
        $store->setup();
    }

    public function testStoreCanSetup()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse('Stored', [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:9401');

        $store = new MessageStore($httpClient, 'http://127.0.0.1:9401', 'test', 'test');

        $store->setup();

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCannotDropOnInvalidResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse('Not Found', [
                'http_code' => 404,
            ]),
        ], 'http://127.0.0.1:9401');

        $store = new MessageStore($httpClient, 'http://127.0.0.1:9401', 'test', 'test');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 404 returned for "http://127.0.0.1:9401/test?auth=test".');
        $this->expectExceptionCode(404);
        $store->drop();
    }

    public function testStoreCanDrop()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse('Deleted', [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:9401');

        $store = new MessageStore($httpClient, 'http://127.0.0.1:9401', 'test', 'test');

        $store->drop();

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCannotSaveOnInvalidResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse('', [
                'http_code' => 400,
            ]),
        ], 'http://127.0.0.1:9401');

        $store = new MessageStore($httpClient, 'http://127.0.0.1:9401', 'test', 'test');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:9401/test?auth=test".');
        $this->expectExceptionCode(400);
        $store->save(new MessageBag(Message::ofUser('Hello there')));
    }

    public function testStoreCanSave()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse('Stored', [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:9401');

        $store = new MessageStore($httpClient, 'http://127.0.0.1:9401', 'test', 'test');

        $store->save(new MessageBag(Message::ofUser('Hello there')));

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCannotLoadMessagesOnInvalidResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse('', [
                'http_code' => 400,
            ]),
        ], 'http://127.0.0.1:9401');

        $store = new MessageStore($httpClient, 'http://127.0.0.1:9401', 'test', 'test');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:9401/test?auth=test".');
        $this->expectExceptionCode(400);
        $store->load();
    }

    public function testStoreCanLoadMessages()
    {
        $serializer = new Serializer([
            new ArrayDenormalizer(),
            new MessageNormalizer(),
        ], [new JsonEncoder()]);

        $payload = $serializer->normalize(Message::ofUser('Hello World'));

        $httpClient = new MockHttpClient([
            new JsonMockResponse([$payload], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:9401');

        $store = new MessageStore($httpClient, 'http://127.0.0.1:9401', 'test', 'test', $serializer);

        $messageBag = $store->load();

        $this->assertCount(1, $messageBag);
        $this->assertSame(1, $httpClient->getRequestsCount());
        $this->assertInstanceOf(UserMessage::class, $messageBag->getUserMessage());
        $this->assertSame('Hello World', $messageBag->getUserMessage()->asText());
    }
}
