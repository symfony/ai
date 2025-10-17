<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat\Tests\Bridge\Pogocache;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Chat\Bridge\Pogocache\MessageStore;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Uid\Uuid;

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

        self::expectException(ClientException::class);
        self::expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:9401/test?auth=test".');
        self::expectExceptionCode(400);
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

        self::expectException(ClientException::class);
        self::expectExceptionMessage('HTTP 404 returned for "http://127.0.0.1:9401/test?auth=test".');
        self::expectExceptionCode(404);
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

        self::expectException(ClientException::class);
        self::expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:9401/test?auth=test".');
        self::expectExceptionCode(400);
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

        self::expectException(ClientException::class);
        self::expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:9401/test?auth=test".');
        self::expectExceptionCode(400);
        $store->load();
    }

    /**
     * @param array<mixed, mixed> $payload
     */
    #[DataProvider('provideMessages')]
    public function testStoreCanLoadMessages(array $payload)
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([$payload], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:9401');

        $store = new MessageStore($httpClient, 'http://127.0.0.1:9401', 'test', 'test');

        $messageBag = $store->load();

        $this->assertCount(1, $messageBag);
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public static function provideMessages(): \Generator
    {
        yield UserMessage::class => [
            [
                'id' => Uuid::v7()->toRfc4122(),
                'type' => UserMessage::class,
                'content' => '',
                'contentAsBase64' => [
                    [
                        'type' => Text::class,
                        'content' => 'What is the Symfony framework?',
                    ],
                ],
                'toolsCalls' => [],
                'metadata' => [],
            ],
        ];
        yield SystemMessage::class => [
            [
                'id' => Uuid::v7()->toRfc4122(),
                'type' => SystemMessage::class,
                'content' => 'Hello there',
                'contentAsBase64' => [],
                'toolsCalls' => [],
                'metadata' => [],
            ],
        ];
        yield AssistantMessage::class => [
            [
                'id' => Uuid::v7()->toRfc4122(),
                'type' => AssistantMessage::class,
                'content' => 'Hello there',
                'contentAsBase64' => [],
                'toolsCalls' => [
                    [
                        'id' => '1',
                        'name' => 'foo',
                        'function' => [
                            'name' => 'foo',
                            'arguments' => '{}',
                        ],
                    ],
                ],
                'metadata' => [],
            ],
        ];
        yield ToolCallMessage::class => [
            [
                'id' => Uuid::v7()->toRfc4122(),
                'type' => ToolCallMessage::class,
                'content' => 'Hello there',
                'contentAsBase64' => [],
                'toolsCalls' => [
                    'id' => '1',
                    'name' => 'foo',
                    'function' => [
                        'name' => 'foo',
                        'arguments' => '{}',
                    ],
                ],
                'metadata' => [],
            ],
        ];
    }
}
