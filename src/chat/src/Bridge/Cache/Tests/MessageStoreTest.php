<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat\Bridge\Cache\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\AI\Chat\Bridge\Cache\MessageStore;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class MessageStoreTest extends TestCase
{
    public function testSetupStoresEmptyMessageBag()
    {
        $cache = new ArrayAdapter();

        $store = new MessageStore($cache, 'test_key');
        $store->setup();

        $this->assertInstanceOf(MessageBag::class, $store->load('test_key'));
    }

    public function testSetupWithCustomTtl()
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cacheItem = $this->createMock(CacheItemInterface::class);

        $cache->method('getItem')->willReturn($cacheItem);
        $cacheItem->method('set')->willReturn($cacheItem);

        $cacheItem->expects($this->once())
            ->method('expiresAfter')
            ->with(3600)
            ->willReturn($cacheItem);

        $cache->expects($this->once())
            ->method('save')
            ->with($cacheItem);

        $store = new MessageStore($cache, 'test_key', 3600);
        $store->setup();
    }

    public function testSaveStoresMessageBag()
    {
        $messageBag = new MessageBag();
        $messageBag->add(Message::ofUser('Test message'));

        $store = new MessageStore(new ArrayAdapter(), 'messages');
        $store->save($messageBag);

        $this->assertCount(1, $store->load());
    }

    public function testLoadReturnsStoredMessages()
    {
        $messageBag = new MessageBag();
        $messageBag->add(Message::ofUser('Cached message'));

        $store = new MessageStore(new ArrayAdapter(), 'test_key');
        $store->save($messageBag);

        $result = $store->load();

        $this->assertSame($messageBag->getId()->toRfc4122(), $result->getId()->toRfc4122());
        $this->assertCount(1, $result);
    }

    public function testLoadReturnsEmptyMessageBagWhenCacheMiss()
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cacheItem = $this->createMock(CacheItemInterface::class);

        $cache->expects($this->once())
            ->method('getItem')
            ->with('test_key')
            ->willReturn($cacheItem);

        $cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(false);

        $cacheItem->expects($this->never())
            ->method('get');

        $store = new MessageStore($cache, 'test_key');
        $result = $store->load();

        $this->assertInstanceOf(MessageBag::class, $result);
        $this->assertCount(0, $result);
    }

    public function testDropDeletesCacheItem()
    {
        $store = new MessageStore(new ArrayAdapter(), 'messages');
        $store->save(new MessageBag(
            Message::ofUser('Hello world'),
        ));

        $this->assertCount(1, $store->load());

        $store->drop();

        $this->assertCount(0, $store->load());
    }
}
