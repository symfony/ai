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
use Symfony\AI\Chat\MessageBagNormalizer;
use Symfony\AI\Chat\MessageNormalizer;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Serializer;
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
        $serializer = new Serializer([
            new ArrayDenormalizer(),
            new MessageBagNormalizer(new MessageNormalizer()),
            new MessageNormalizer(),
        ], [new JsonEncoder()]);

        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->never())->method('exists');
        $redis->expects($this->once())->method('set');

        $store = new MessageStore($redis, 'test', $serializer);
        $store->save(new MessageBag(Message::ofUser('Hello there')));
    }

    public function testStoreCanLoad()
    {
        $serializer = new Serializer([
            new ArrayDenormalizer(),
            new MessageBagNormalizer(new MessageNormalizer()),
            new MessageNormalizer(),
        ], [new JsonEncoder()]);

        $messageBag = new MessageBag(Message::ofUser('Hello there'));

        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->never())->method('exists');
        $redis->expects($this->once())->method('set');
        $redis->expects($this->once())->method('get')->willReturn($serializer->serialize($messageBag, 'json'));

        $store = new MessageStore($redis, 'test', $serializer);
        $store->save($messageBag);

        $messageBag = $store->load();

        $this->assertCount(1, $messageBag);
    }
}
