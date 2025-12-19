<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat\Bridge\Redis\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Chat\Bridge\Redis\MessageStore;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\Component\Serializer\SerializerInterface;

final class MessageStoreTest extends TestCase
{
    public function testStoreCannotSetupOnExistingItem()
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->never())->method('serialize');

        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())->method('exists')->willReturn(true);

        $store = new MessageStore($redis, 'test', $serializer);
        $store->setup();
    }

    public function testStoreCanSetup()
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())->method('serialize')->willReturn('');

        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())->method('exists')->willReturn(false);
        $redis->expects($this->once())->method('set');

        $store = new MessageStore($redis, 'test', $serializer);
        $store->setup();
    }

    public function testStoreCanDrop()
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())->method('serialize')->willReturn('');

        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->never())->method('exists');
        $redis->expects($this->once())->method('set');

        $store = new MessageStore($redis, 'test', $serializer);
        $store->drop();
    }

    public function testStoreCanSave()
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())->method('serialize')->willReturn('[{"id":"019a24c5-67a3-7f08-a670-5d30958d439f","type":"Symfony\\AI\\Platform\\Message\\SystemMessage","content":"You are a helpful assistant. You only answer with short sentences.","contentAsBase64":[],"toolsCalls":[],"metadata":[],"addedAt":1761553508}]');

        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->never())->method('exists');
        $redis->expects($this->once())->method('set');

        $store = new MessageStore($redis, 'test', $serializer);
        $store->save(new MessageBag(Message::ofUser('Hello there')));
    }

    public function testStoreCanLoad()
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())->method('serialize')->willReturn('[{"id":"019a24c5-67a3-7f08-a670-5d30958d439f","type":"Symfony\\AI\\Platform\\Message\\SystemMessage","content":"You are a helpful assistant. You only answer with short sentences.","contentAsBase64":[],"toolsCalls":[],"metadata":[],"addedAt":1761553508}]');
        $serializer->expects($this->once())->method('deserialize')->willReturn([
            new SystemMessage('You are a helpful assistant. You only answer with short sentences.'),
        ]);

        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->never())->method('exists');
        $redis->expects($this->once())->method('set');

        $store = new MessageStore($redis, 'test', $serializer);
        $store->save(new MessageBag(Message::ofUser('Hello there')));

        $messageBag = $store->load();

        $this->assertCount(1, $messageBag);
    }
}
