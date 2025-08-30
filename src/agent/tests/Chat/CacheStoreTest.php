<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Chat;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Chat\MessageStore\CacheStore;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

#[CoversClass(CacheStore::class)]
#[UsesClass(MessageBag::class)]
#[UsesClass(Message::class)]
final class CacheStoreTest extends TestCase
{
    public function testItCanStore()
    {
        $messageBag = new MessageBag();
        $messageBag->add(Message::ofUser('Hello'));

        $store = new CacheStore(new ArrayAdapter());
        $store->save($messageBag);

        $this->assertCount(1, $store->load('_message_store_cache'));
    }

    public function testItCanStoreMultipleMessageBags()
    {
        $firstMessageBag = new MessageBag();
        $firstMessageBag->add(Message::ofUser('Hello'));

        $secondMessageBag = new MessageBag();
        $secondMessageBag->add(Message::ofUser('Hello'));
        $secondMessageBag->add(Message::ofUser('Hello'));

        $store = new CacheStore(new ArrayAdapter());
        $store->save($firstMessageBag, 'foo');
        $store->save($secondMessageBag, 'bar');

        $this->assertCount(1, $store->load('foo'));
        $this->assertCount(2, $store->load('bar'));
        $this->assertCount(0, $store->load('_message_store_cache'));
    }

    public function testItCanClear()
    {
        $bag = new MessageBag();
        $bag->add(Message::ofUser('Hello'));
        $bag->add(Message::ofUser('Hello'));

        $store = new CacheStore(new ArrayAdapter());
        $store->save($bag);

        $this->assertCount(2, $store->load('_message_store_cache'));

        $store->clear();

        $this->assertCount(0, $store->load('_message_store_cache'));
    }
}
