<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\SurrealDb\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Bridge\SurrealDb\MessageStore;
use Symfony\AI\Agent\Exception\ConflictException;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Serializer\MessageBagNormalizer;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Uid\Uuid;

final class MessageStoreTest extends TestCase
{
    public function testSetupRejectsOptions()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No supported options.');

        $this->store(new MockHttpClient())->setup(['foo' => 'bar']);
    }

    public function testDropDeletesTheTable()
    {
        $httpClient = new MockHttpClient([
            $this->signin(),
            $this->expectQuery('DELETE type::table("chat");', 'OK', []),
        ]);

        $this->store($httpClient)->drop();

        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testLoadReturnsAnEmptyBagWithTheGivenId()
    {
        $id = Uuid::v7();
        $httpClient = new MockHttpClient([
            $this->signin(),
            $this->expectQuery(\sprintf('SELECT version, messages FROM ONLY type::thing("chat", "%s");', $id->toRfc4122()), 'OK', null),
        ]);

        $messages = $this->store($httpClient)->load($id);

        $this->assertCount(0, $messages);
        $this->assertTrue($id->equals($messages->getId()));
    }

    public function testLoadDeserializesTheStoredConversation()
    {
        $messages = new MessageBag(Message::ofUser('Hello'), Message::ofAssistant('Hi'));
        $payload = $this->serializer()->serialize($messages, 'json');
        $httpClient = new MockHttpClient([
            $this->signin(),
            new JsonMockResponse([['status' => 'OK', 'result' => ['version' => '', 'messages' => $payload]]]),
        ]);

        $this->assertSame($payload, $this->serializer()->serialize($this->store($httpClient)->load($messages->getId()), 'json'));
    }

    public function testNewConversationIsCreated()
    {
        $answer = Message::ofAssistant('Hi');
        $messages = new MessageBag(Message::ofUser('Hello'), $answer);
        $content = json_encode(['version' => $answer->getId()->toRfc4122(), 'messages' => $this->serializer()->serialize($messages, 'json')], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        $httpClient = new MockHttpClient([
            $this->signin(),
            new JsonMockResponse([['status' => 'OK', 'result' => null]]),
            $this->expectQuery(\sprintf('CREATE type::thing("chat", "%s") CONTENT %s;', $messages->getId()->toRfc4122(), $content), 'OK', [[]]),
        ]);

        $this->store($httpClient)->save($messages);

        $this->assertSame(3, $httpClient->getRequestsCount());
    }

    public function testCreatingAConversationCreatedInTheMeantimeConflicts()
    {
        $httpClient = new MockHttpClient([
            $this->signin(),
            new JsonMockResponse([['status' => 'OK', 'result' => null]]),
            new JsonMockResponse([['status' => 'ERR', 'result' => 'Database record already exists']]),
        ]);

        $this->expectException(ConflictException::class);

        $this->store($httpClient)->save(new MessageBag(Message::ofUser('Hello')));
    }

    public function testStoredConversationIsUpdatedWhileItsVersionIsUnchanged()
    {
        $question = Message::ofUser('Hello');
        $stored = new MessageBag($question);
        $messages = $stored->with($answer = Message::ofAssistant('Hi'));
        $content = json_encode(['version' => $answer->getId()->toRfc4122(), 'messages' => $this->serializer()->serialize($messages, 'json')], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        $httpClient = new MockHttpClient([
            $this->signin(),
            new JsonMockResponse([['status' => 'OK', 'result' => ['version' => $question->getId()->toRfc4122(), 'messages' => $this->serializer()->serialize($stored, 'json')]]]),
            $this->expectQuery(\sprintf('UPDATE type::thing("chat", "%s") MERGE %s WHERE version = "%s" RETURN AFTER;', $messages->getId()->toRfc4122(), $content, $question->getId()->toRfc4122()), 'OK', [[]]),
        ]);

        $this->store($httpClient)->save($messages);

        $this->assertSame(3, $httpClient->getRequestsCount());
    }

    public function testSaveConflictsWhenTheVersionChangedInTheMeantime()
    {
        $stored = new MessageBag(Message::ofUser('Hello'));
        $httpClient = new MockHttpClient([
            $this->signin(),
            new JsonMockResponse([['status' => 'OK', 'result' => ['version' => '', 'messages' => $this->serializer()->serialize($stored, 'json')]]]),
            new JsonMockResponse([['status' => 'OK', 'result' => []]]),
        ]);

        $this->expectException(ConflictException::class);

        $this->store($httpClient)->save($stored->with(Message::ofAssistant('Hi')));
    }

    public function testSaveConflictsWhenTheMessagesDiverge()
    {
        $messages = new MessageBag(Message::ofUser('Hello'));
        $httpClient = new MockHttpClient([
            $this->signin(),
            new JsonMockResponse([['status' => 'OK', 'result' => ['version' => '', 'messages' => $this->serializer()->serialize($messages->with(Message::ofAssistant('Hi')), 'json')]]]),
        ]);

        $this->expectException(ConflictException::class);

        $this->store($httpClient)->save($messages->with(Message::ofAssistant('Hey')));
    }

    public function testFailingQueriesThrow()
    {
        $httpClient = new MockHttpClient([
            $this->signin(),
            new JsonMockResponse([['status' => 'ERR', 'result' => 'There was a problem with the database']]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The SurrealDB query failed: "There was a problem with the database"');

        $this->store($httpClient)->load(Uuid::v7());
    }

    public function testDeleteRemovesTheConversationRecord()
    {
        $id = Uuid::v7();
        $httpClient = new MockHttpClient([
            $this->signin(),
            $this->expectQuery(\sprintf('DELETE type::thing("chat", "%s");', $id->toRfc4122()), 'OK', []),
        ]);

        $this->store($httpClient)->delete($id);

        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    private function store(MockHttpClient $httpClient): MessageStore
    {
        return new MessageStore($httpClient, 'http://127.0.0.1:8000', 'user', 'pass', 'ns', 'db', table: 'chat');
    }

    private function signin(): JsonMockResponse
    {
        return new JsonMockResponse(['code' => 200, 'details' => 'Authentication succeeded.', 'token' => 'bar']);
    }

    private function expectQuery(string $statement, string $status, mixed $result): \Closure
    {
        return function (string $method, string $url, array $options) use ($statement, $status, $result): JsonMockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('http://127.0.0.1:8000/sql', $url);
            $this->assertSame($statement, $options['body']);
            $this->assertContains('Surreal-NS: ns', $options['headers']);
            $this->assertContains('Authorization: Bearer bar', $options['headers']);

            return new JsonMockResponse([['status' => $status, 'result' => $result]]);
        };
    }

    private function serializer(): Serializer
    {
        return new Serializer([new MessageBagNormalizer()], [new JsonEncoder()]);
    }
}
