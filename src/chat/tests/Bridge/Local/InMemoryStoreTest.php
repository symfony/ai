<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat\Tests\Bridge\Local;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Chat\Bridge\Local\InMemoryStore;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

#[CoversClass(InMemoryStore::class)]
#[UsesClass(MessageBag::class)]
#[UsesClass(Message::class)]
final class InMemoryStoreTest extends TestCase
{
    public function testItCanStore()
    {
        $messageBag = new MessageBag();
        $messageBag->add(Message::ofUser('Hello'));

        $store = new InMemoryStore();
        $store->save($messageBag);

        $this->assertCount(1, $store->load('_message_store_memory'));
    }

    public function testItCanStoreMultipleMessageBags()
    {
        $firstMessageBag = new MessageBag();
        $firstMessageBag->add(Message::ofUser('Hello'));

        $secondMessageBag = new MessageBag();
        $secondMessageBag->add(Message::ofUser('Hello'));
        $secondMessageBag->add(Message::ofUser('Hello'));

        $store = new InMemoryStore();
        $store->save($firstMessageBag, 'foo');
        $store->save($secondMessageBag, 'bar');

        $this->assertCount(1, $store->load('foo'));
        $this->assertCount(2, $store->load('bar'));
        $this->assertCount(0, $store->load('_message_store_memory'));
    }

    public function testItCanClear()
    {
        $bag = new MessageBag();
        $bag->add(Message::ofUser('Hello'));
        $bag->add(Message::ofUser('Hello'));

        $store = new InMemoryStore();
        $store->save($bag);

        $this->assertCount(2, $store->load('_message_store_memory'));

        $store->clear();

        $this->assertCount(0, $store->load('_message_store_memory'));
    }
}
