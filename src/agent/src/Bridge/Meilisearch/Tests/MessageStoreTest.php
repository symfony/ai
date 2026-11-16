<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Meilisearch\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Bridge\Meilisearch\MessageStore;
use Symfony\AI\Agent\Exception\ConflictException;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Serializer\MessageBagNormalizer;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Uid\Uuid;

final class MessageStoreTest extends TestCase
{
    public function testSetupCreatesTheIndexAndWaitsForTheTask()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['taskUid' => 1, 'status' => 'enqueued'], ['http_code' => 202]),
            new JsonMockResponse(['uid' => 1, 'status' => 'processing']),
            new JsonMockResponse(['uid' => 1, 'status' => 'succeeded']),
        ]);

        $this->store($httpClient)->setup();

        $this->assertSame(3, $httpClient->getRequestsCount());
    }

    public function testSetupKeepsAnExistingIndex()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['taskUid' => 1, 'status' => 'enqueued'], ['http_code' => 202]),
            new JsonMockResponse(['uid' => 1, 'status' => 'failed', 'error' => ['code' => 'index_already_exists', 'message' => 'Index already exists.']]),
        ]);

        $this->store($httpClient)->setup();

        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testLoadReturnsAnEmptyBagWithTheGivenId()
    {
        $id = Uuid::v7();
        $httpClient = new MockHttpClient(static function (string $method, string $url) use ($id): JsonMockResponse {
            self::assertSame('GET', $method);
            self::assertSame('http://127.0.0.1:7700/indexes/chat/documents/'.$id->toRfc4122(), $url);

            return new JsonMockResponse(['code' => 'document_not_found'], ['http_code' => 404]);
        });

        $messages = $this->store($httpClient)->load($id);

        $this->assertCount(0, $messages);
        $this->assertTrue($id->equals($messages->getId()));
    }

    public function testLoadDeserializesTheStoredConversation()
    {
        $messages = new MessageBag(Message::ofUser('Hello'), Message::ofAssistant('Hi'));
        $payload = $this->serializer()->serialize($messages, 'json');
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['id' => $messages->getId()->toRfc4122(), 'version' => '', 'messages' => $payload]),
        ]);

        $this->assertSame($payload, $this->serializer()->serialize($this->store($httpClient)->load($messages->getId()), 'json'));
    }

    public function testSaveWritesTheConversationAsDocument()
    {
        $answer = Message::ofAssistant('Hi');
        $messages = new MessageBag(Message::ofUser('Hello'), $answer);
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['code' => 'document_not_found'], ['http_code' => 404]),
            function (string $method, string $url, array $options) use ($messages, $answer): JsonMockResponse {
                $this->assertSame('PUT', $method);
                $this->assertSame('http://127.0.0.1:7700/indexes/chat/documents', $url);
                $this->assertSame([[
                    'id' => $messages->getId()->toRfc4122(),
                    'version' => $answer->getId()->toRfc4122(),
                    'messages' => $this->serializer()->serialize($messages, 'json'),
                ]], json_decode($options['body'], true));

                return new JsonMockResponse(['taskUid' => 2, 'status' => 'enqueued'], ['http_code' => 202]);
            },
            new JsonMockResponse(['uid' => 2, 'status' => 'succeeded']),
        ]);

        $this->store($httpClient)->save($messages);

        $this->assertSame(3, $httpClient->getRequestsCount());
    }

    public function testSaveConflictsWhenTheMessagesDiverge()
    {
        $messages = new MessageBag(Message::ofUser('Hello'));
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['messages' => $this->serializer()->serialize($messages->with(Message::ofAssistant('Hi')), 'json')]),
        ]);

        $this->expectException(ConflictException::class);

        $this->store($httpClient)->save($messages->with(Message::ofAssistant('Hey')));
    }

    public function testSaveFailsWhenTheTaskFails()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['code' => 'document_not_found'], ['http_code' => 404]),
            new JsonMockResponse(['taskUid' => 2, 'status' => 'enqueued'], ['http_code' => 202]),
            new JsonMockResponse(['uid' => 2, 'status' => 'failed', 'error' => ['code' => 'index_not_found', 'message' => 'Index `chat` not found.']]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The Meilisearch task "2" failed: "Index `chat` not found."');

        $this->store($httpClient)->save(new MessageBag(Message::ofUser('Hello')));
    }

    public function testDropDeletesAllDocuments()
    {
        $httpClient = new MockHttpClient([
            static function (string $method, string $url): JsonMockResponse {
                self::assertSame('DELETE', $method);
                self::assertSame('http://127.0.0.1:7700/indexes/chat/documents', $url);

                return new JsonMockResponse(['taskUid' => 3, 'status' => 'enqueued'], ['http_code' => 202]);
            },
            new JsonMockResponse(['uid' => 3, 'status' => 'succeeded']),
        ]);

        $this->store($httpClient)->drop();

        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testDeleteRemovesTheConversationDocument()
    {
        $id = Uuid::v7();
        $httpClient = new MockHttpClient([
            static function (string $method, string $url) use ($id): JsonMockResponse {
                self::assertSame('DELETE', $method);
                self::assertSame('http://127.0.0.1:7700/indexes/chat/documents/'.$id->toRfc4122(), $url);

                return new JsonMockResponse(['taskUid' => 4, 'status' => 'enqueued'], ['http_code' => 202]);
            },
            new JsonMockResponse(['uid' => 4, 'status' => 'succeeded']),
        ]);

        $this->store($httpClient)->delete($id);

        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    private function store(MockHttpClient $httpClient): MessageStore
    {
        return new MessageStore($httpClient, 'http://127.0.0.1:7700', 'key', new MockClock(), 'chat');
    }

    private function serializer(): Serializer
    {
        return new Serializer([new MessageBagNormalizer()], [new JsonEncoder()]);
    }
}
