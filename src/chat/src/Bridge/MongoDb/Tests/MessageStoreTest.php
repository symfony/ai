<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat\Bridge\MongoDb\Tests;

use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\Driver\CursorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Chat\Bridge\MongoDb\MessageStore;
use Symfony\AI\Chat\MessageBagNormalizer;
use Symfony\AI\Chat\MessageNormalizer;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Serializer;

final class MessageStoreTest extends TestCase
{
    public function testStoreCanSetup()
    {
        $database = $this->createMock(Database::class);
        $database->expects($this->once())->method('createCollection')->with('bar');

        $client = $this->createMock(Client::class);
        $client->expects($this->once())->method('getDatabase')->willReturn($database);

        $messageStore = new MessageStore($client, 'foo', 'bar');
        $messageStore->setup();
    }

    public function testStoreCanDrop()
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())->method('deleteMany')->with([
            'q' => [],
        ]);

        $client = $this->createMock(Client::class);
        $client->expects($this->once())->method('getCollection')->willReturn($collection);

        $messageStore = new MessageStore($client, 'foo', 'bar');
        $messageStore->drop();
    }

    public function testMessageStoreCanSaveWhileNotCreatingExistingCollection()
    {
        $messageBag = new MessageBag(Message::ofUser('Hello world'));

        $serializer = new Serializer([
            new ArrayDenormalizer(),
            new MessageBagNormalizer(new MessageNormalizer()),
            new MessageNormalizer(),
        ], [new JsonEncoder()]);

        $payload = $serializer->normalize($messageBag, context: [
            'identifier' => '_id',
        ]);

        $this->assertArrayHasKey('_id', $payload);

        $database = $this->createMock(Database::class);
        $database->expects($this->once())->method('listCollectionNames')->willReturn(new \ArrayIterator(['foo', 'bar', 'random']));
        $database->expects($this->never())->method('createCollection');

        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())->method('insertOne')->with($payload);

        $client = $this->createMock(Client::class);
        $client->expects($this->once())->method('getDatabase')->willReturn($database);
        $client->expects($this->once())->method('getCollection')->willReturn($collection);

        $messageStore = new MessageStore($client, 'foo', 'bar', $serializer);
        $messageStore->save($messageBag, 'random');
    }

    public function testMessageStoreCanSaveWhileCreatingUndefinedCollection()
    {
        $messageBag = new MessageBag(Message::ofUser('Hello world'));

        $serializer = new Serializer([
            new ArrayDenormalizer(),
            new MessageBagNormalizer(new MessageNormalizer()),
            new MessageNormalizer(),
        ], [new JsonEncoder()]);

        $payload = $serializer->normalize($messageBag, context: [
            'identifier' => '_id',
        ]);

        $this->assertArrayHasKey('_id', $payload);

        $database = $this->createMock(Database::class);
        $database->expects($this->once())->method('listCollectionNames')->willReturn(new \ArrayIterator(['foo', 'bar']));
        $database->expects($this->once())->method('createCollection')->with('random');

        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())->method('insertOne')->with($payload);

        $client = $this->createMock(Client::class);
        $client->expects($this->once())->method('getDatabase')->willReturn($database);
        $client->expects($this->once())->method('getCollection')->willReturn($collection);

        $messageStore = new MessageStore($client, 'foo', 'bar', $serializer);
        $messageStore->save($messageBag, 'random');
    }

    public function testMessageStoreCanSave()
    {
        $bag = new MessageBag(Message::ofUser('Hello world'));

        $serializer = new Serializer([
            new ArrayDenormalizer(),
            new MessageBagNormalizer(new MessageNormalizer()),
            new MessageNormalizer(),
        ], [new JsonEncoder()]);

        $payload = $serializer->normalize($bag, context: [
            'identifier' => '_id',
        ]);

        $this->assertArrayHasKey('_id', $payload);

        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())->method('insertOne')->with($payload);

        $client = $this->createMock(Client::class);
        $client->expects($this->once())->method('getCollection')->willReturn($collection);

        $messageStore = new MessageStore($client, 'foo', 'bar', $serializer);
        $messageStore->save($bag);
    }

    public function testMessageStoreCanLoad()
    {
        $serializer = new Serializer([
            new ArrayDenormalizer(),
            new MessageBagNormalizer(new MessageNormalizer()),
            new MessageNormalizer(),
        ], [new JsonEncoder()]);

        $messageBag = new MessageBag(
            Message::ofUser('Hello world'),
        );

        $payload = $serializer->normalize($messageBag, context: ['identifier' => '_id']);

        $cursor = $this->createMock(CursorInterface::class);
        $cursor->expects($this->once())->method('toArray')->willReturn($payload);

        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())->method('find')->willReturn($cursor);

        $client = $this->createMock(Client::class);
        $client->expects($this->once())->method('getCollection')->willReturn($collection);

        $messageStore = new MessageStore($client, 'foo', 'bar', $serializer);

        $messages = $messageStore->load();

        $this->assertSame($messageBag->getId()->toRfc4122(), $messages->getId()->toRfc4122());
        $this->assertCount(1, $messages);
    }
}
