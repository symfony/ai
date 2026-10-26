<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\MongoDb\Tests;

use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\InsertOneResult;
use MongoDB\UpdateResult;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Bridge\MongoDb\MessageStore;
use Symfony\AI\Agent\Exception\ConflictException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Serializer\MessageBagNormalizer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Uid\Uuid;

final class MessageStoreTest extends TestCase
{
    public function testStoreCanSetup()
    {
        $database = $this->createMock(Database::class);
        $database->expects($this->once())->method('createCollection')->with('bar', ['capped' => false]);

        $client = $this->createMock(Client::class);
        $client->expects($this->once())->method('getDatabase')->with('foo')->willReturn($database);

        (new MessageStore($client, 'foo', 'bar'))->setup(['capped' => false]);
    }

    public function testStoreCanDrop()
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())->method('deleteMany')->with([]);

        (new MessageStore($this->client($collection), 'foo', 'bar'))->drop();
    }

    public function testLoadReturnsAnEmptyBagWithTheGivenId()
    {
        $id = Uuid::v7();
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())->method('findOne')->with(['_id' => $id->toRfc4122()])->willReturn(null);

        $messages = (new MessageStore($this->client($collection), 'foo', 'bar'))->load($id);

        $this->assertCount(0, $messages);
        $this->assertTrue($id->equals($messages->getId()));
    }

    public function testLoadDeserializesTheStoredConversation()
    {
        $messages = new MessageBag(Message::ofUser('Hello'), Message::ofAssistant('Hi'));
        $payload = $this->serializer()->serialize($messages, 'json');
        $collection = $this->createStub(Collection::class);
        $collection->method('findOne')->willReturn(['_id' => $messages->getId()->toRfc4122(), 'version' => '', 'messages' => $payload]);

        $loaded = (new MessageStore($this->client($collection), 'foo', 'bar'))->load($messages->getId());

        $this->assertSame($payload, $this->serializer()->serialize($loaded, 'json'));
    }

    public function testNewConversationIsInserted()
    {
        $answer = Message::ofAssistant('Hi');
        $messages = new MessageBag(Message::ofUser('Hello'), $answer);
        $collection = $this->createMock(Collection::class);
        $collection->method('findOne')->willReturn(null);
        $collection->expects($this->once())->method('insertOne')->with([
            '_id' => $messages->getId()->toRfc4122(),
            'version' => $answer->getId()->toRfc4122(),
            'messages' => $this->serializer()->serialize($messages, 'json'),
        ])->willReturn($this->createStub(InsertOneResult::class));

        (new MessageStore($this->client($collection), 'foo', 'bar'))->save($messages);
    }

    public function testStoredConversationIsUpdatedWhileItsVersionIsUnchanged()
    {
        $question = Message::ofUser('Hello');
        $stored = new MessageBag($question);
        $messages = $stored->with($answer = Message::ofAssistant('Hi'));
        $result = $this->createStub(UpdateResult::class);
        $result->method('getMatchedCount')->willReturn(1);
        $collection = $this->createMock(Collection::class);
        $collection->method('findOne')->willReturn(['version' => $question->getId()->toRfc4122(), 'messages' => $this->serializer()->serialize($stored, 'json')]);
        $collection->expects($this->once())->method('updateOne')->with(
            ['_id' => $messages->getId()->toRfc4122(), 'version' => $question->getId()->toRfc4122()],
            ['$set' => ['version' => $answer->getId()->toRfc4122(), 'messages' => $this->serializer()->serialize($messages, 'json')]],
        )->willReturn($result);

        (new MessageStore($this->client($collection), 'foo', 'bar'))->save($messages);
    }

    public function testSaveConflictsWhenTheVersionChangedInTheMeantime()
    {
        $stored = new MessageBag(Message::ofUser('Hello'));
        $result = $this->createStub(UpdateResult::class);
        $result->method('getMatchedCount')->willReturn(0);
        $collection = $this->createStub(Collection::class);
        $collection->method('findOne')->willReturn(['version' => '', 'messages' => $this->serializer()->serialize($stored, 'json')]);
        $collection->method('updateOne')->willReturn($result);

        $this->expectException(ConflictException::class);

        (new MessageStore($this->client($collection), 'foo', 'bar'))->save($stored->with(Message::ofAssistant('Hi')));
    }

    public function testSaveConflictsWhenTheMessagesDiverge()
    {
        $messages = new MessageBag(Message::ofUser('Hello'));
        $collection = $this->createMock(Collection::class);
        $collection->method('findOne')->willReturn(['version' => '', 'messages' => $this->serializer()->serialize($messages->with(Message::ofAssistant('Hi')), 'json')]);
        $collection->expects($this->never())->method('updateOne');

        $this->expectException(ConflictException::class);

        (new MessageStore($this->client($collection), 'foo', 'bar'))->save($messages->with(Message::ofAssistant('Hey')));
    }

    public function testDeleteRemovesTheConversationDocument()
    {
        $id = Uuid::v7();
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())->method('deleteOne')->with(['_id' => $id->toRfc4122()]);

        (new MessageStore($this->client($collection), 'foo', 'bar'))->delete($id);
    }

    private function client(Collection $collection): Client
    {
        $client = $this->createStub(Client::class);
        $client->method('getCollection')->willReturn($collection);

        return $client;
    }

    private function serializer(): Serializer
    {
        return new Serializer([new MessageBagNormalizer()], [new JsonEncoder()]);
    }
}
