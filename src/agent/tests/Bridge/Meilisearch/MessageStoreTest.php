<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Bridge\Meilisearch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Bridge\Meilisearch\MessageStore;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

#[CoversClass(MessageStore::class)]
final class MessageStoreTest extends TestCase
{
    public function testStoreCannotInitializeOnInvalidResponse()
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
        ], 'http://localhost:7700');

        $store = new MessageStore(
            $httpClient,
            'http://localhost:7700',
            'test',
            'test',
        );

        self::expectException(ClientException::class);
        self::expectExceptionMessage('HTTP 400 returned for "http://localhost:7700/indexes".');
        self::expectExceptionCode(400);
        $store->initialize();
    }

    public function testStoreCanInitialize()
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
                'status' => 'enqueued',
                'type' => 'indexUpdate',
                'enqueuedAt' => '2025-01-01T01:00:00Z',
            ], [
                'http_code' => 202,
            ]),
        ], 'http://localhost:7700');

        $store = new MessageStore(
            $httpClient,
            'http://localhost:7700',
            'test',
            'test',
        );

        $store->initialize();

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCannotAddOnInvalidResponse()
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
        ], 'http://localhost:7700');

        $store = new MessageStore(
            $httpClient,
            'http://localhost:7700',
            'test',
            'test',
        );

        self::expectException(ClientException::class);
        self::expectExceptionMessage('HTTP 400 returned for "http://localhost:7700/indexes/test/documents".');
        self::expectExceptionCode(400);
        $store->save(new MessageBag(Message::ofUser('Hello there')));
    }

    public function testStoreCanAdd()
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
        ], 'http://localhost:7700');

        $store = new MessageStore(
            $httpClient,
            'http://localhost:7700',
            'test',
            'test',
        );

        $store->save(new MessageBag(Message::ofUser('Hello there')));

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testStoreCannotRetrieveMessagesOnInvalidResponse()
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
        ], 'http://localhost:7700');

        $store = new MessageStore(
            $httpClient,
            'http://localhost:7700',
            'test',
            'test',
        );

        self::expectException(ClientException::class);
        self::expectExceptionMessage('HTTP 400 returned for "http://localhost:7700/indexes/test/documents/fetch".');
        self::expectExceptionCode(400);
        $store->load();
    }
}
