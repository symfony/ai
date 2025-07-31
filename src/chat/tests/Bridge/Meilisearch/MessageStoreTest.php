<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat\Tests\Bridge\Meilisearch;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Chat\Bridge\Meilisearch\MessageStore;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Uid\Uuid;

final class MessageStoreTest extends TestCase
{
    public function testStoreCannotSetupOnInvalidResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'message' => 'error',
                'code' => 'index_creation_failed',
                'type' => 'invalid_request',
                'link' => 'https://docs.meilisearch.com/errors#index_creation_failed',
            ], [
                'http_code' => 400,
            ]),
        ], 'http://127.0.0.1:7700');

        $store = new MessageStore($httpClient, 'http://127.0.0.1:7700', 'test', new MonotonicClock(), 'test');

        self::expectException(ClientException::class);
        self::expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:7700/indexes".');
        self::expectExceptionCode(400);
        $store->setup();
    }

    public function testStoreCanSetup()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'taskUid' => 1,
                'indexUid' => 'test',
                'status' => 'enqueued',
                'type' => 'indexCreation',
                'enqueuedAt' => '2025-01-01T00:00:00Z',
            ], [
                'http_code' => 202,
            ]),
            new JsonMockResponse([
                'taskUid' => 2,
                'indexUid' => 'test',
                'status' => 'succeeded',
                'type' => 'indexCreation',
                'enqueuedAt' => '2025-01-01T00:00:00Z',
            ], [
                'http_code' => 202,
            ]),
            new JsonMockResponse([
                'taskUid' => 3,
                'indexUid' => 'test',
                'status' => 'enqueued',
                'type' => 'indexUpdate',
                'enqueuedAt' => '2025-01-01T01:00:00Z',
            ], [
                'http_code' => 202,
            ]),
            new JsonMockResponse([
                'taskUid' => 4,
                'indexUid' => 'test',
                'status' => 'succeeded',
                'type' => 'indexUpdate',
                'enqueuedAt' => '2025-01-01T00:00:00Z',
            ], [
                'http_code' => 202,
            ]),
        ], 'http://127.0.0.1:7700');

        $store = new MessageStore($httpClient, 'http://127.0.0.1:7700', 'test', new MonotonicClock(), 'test');

        $store->setup();

        $this->assertSame(4, $httpClient->getRequestsCount());
    }

    public function testStoreCannotDropOnInvalidResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'message' => 'error',
                'code' => 'index_not_found',
                'type' => 'invalid_request',
                'link' => 'https://docs.meilisearch.com/errors#index_not_found',
            ], [
                'http_code' => 400,
            ]),
        ], 'http://127.0.0.1:7700');

        $store = new MessageStore($httpClient, 'http://127.0.0.1:7700', 'test', new MonotonicClock(), 'test');

        self::expectException(ClientException::class);
        self::expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:7700/indexes/test/documents".');
        self::expectExceptionCode(400);
        $store->drop();
    }

    public function testStoreCanDrop()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'taskUid' => 1,
                'indexUid' => 'test',
                'status' => 'enqueued',
                'type' => 'indexDeletion',
                'enqueuedAt' => '2025-01-01T00:00:00Z',
            ], [
                'http_code' => 202,
            ]),
            new JsonMockResponse([
                'taskUid' => 2,
                'indexUid' => 'test',
                'status' => 'succeeded',
                'type' => 'indexDeletion',
                'enqueuedAt' => '2025-01-01T00:00:00Z',
            ], [
                'http_code' => 202,
            ]),
        ], 'http://127.0.0.1:7700');

        $store = new MessageStore($httpClient, 'http://127.0.0.1:7700', 'test', new MonotonicClock(), 'test');

        $store->drop();

        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testStoreCannotSaveOnInvalidResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'message' => 'error',
                'code' => 'invalid_document_fields',
                'type' => 'invalid_request',
                'link' => 'https://docs.meilisearch.com/errors#invalid_document_fields',
            ], [
                'http_code' => 400,
            ]),
        ], 'http://127.0.0.1:7700');

        $store = new MessageStore($httpClient, 'http://127.0.0.1:7700', 'test', new MonotonicClock(), 'test');

        self::expectException(ClientException::class);
        self::expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:7700/indexes/test/documents".');
        self::expectExceptionCode(400);
        $store->save(new MessageBag(Message::ofUser('Hello there')));
    }

    public function testStoreCanSave()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'taskUid' => 1,
                'indexUid' => 'test',
                'status' => 'enqueued',
                'type' => 'documentAdditionOrUpdate',
                'enqueuedAt' => '2025-01-01T00:00:00Z',
            ], [
                'http_code' => 202,
            ]),
            new JsonMockResponse([
                'taskUid' => 2,
                'indexUid' => 'test',
                'status' => 'succeeded',
                'type' => 'documentAdditionOrUpdate',
                'enqueuedAt' => '2025-01-01T00:00:00Z',
            ], [
                'http_code' => 202,
            ]),
        ], 'http://127.0.0.1:7700');

        $store = new MessageStore($httpClient, 'http://127.0.0.1:7700', 'test', new MonotonicClock(), 'test');

        $store->save(new MessageBag(Message::ofUser('Hello there')));

        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testStoreCannotLoadMessagesOnInvalidResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'message' => 'error',
                'code' => 'document_not_found',
                'type' => 'invalid_request',
                'link' => 'https://docs.meilisearch.com/errors#document_not_found',
            ], [
                'http_code' => 400,
            ]),
        ], 'http://127.0.0.1:7700');

        $store = new MessageStore($httpClient, 'http://127.0.0.1:7700', 'test', new MonotonicClock(), 'test');

        self::expectException(ClientException::class);
        self::expectExceptionMessage('HTTP 400 returned for "http://127.0.0.1:7700/indexes/test/documents/fetch".');
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
            new JsonMockResponse([
                'results' => [
                    $payload,
                ],
            ], [
                'http_code' => 200,
            ]),
        ], 'http://127.0.0.1:7700');

        $store = new MessageStore($httpClient, 'http://127.0.0.1:7700', 'test', new MonotonicClock(), 'test');

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
