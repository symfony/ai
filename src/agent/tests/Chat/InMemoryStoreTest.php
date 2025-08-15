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
use Symfony\AI\Agent\Chat\MessageStore\InMemoryStore;
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

        $this->assertCount(1, $store->load($messageBag->getId()));
    }

    public function testItCanStoreMultipleMessageBags()
    {
        $firstMessageBag = new MessageBag();
        $firstMessageBag->add(Message::ofUser('Hello'));

        $secondMessageBag = new MessageBag();

        $store = new InMemoryStore();
        $store->save($firstMessageBag);
        $store->save($secondMessageBag);

        $this->assertCount(1, $store->load($firstMessageBag->getId()));
        $this->assertCount(0, $store->load($secondMessageBag->getId()));
    }

    public function testItCanClear()
    {
        $firstMessageBag = new MessageBag();
        $firstMessageBag->add(Message::ofUser('Hello'));

        $secondMessageBag = new MessageBag();

        $store = new InMemoryStore();
        $store->save($firstMessageBag);
        $store->save($secondMessageBag);

        $this->assertCount(1, $store->load($firstMessageBag->getId()));
        $this->assertCount(0, $store->load($secondMessageBag->getId()));

        $store->clear();

        $this->assertCount(0, $store->load($firstMessageBag->getId()));
        $this->assertCount(0, $store->load($secondMessageBag->getId()));
    }
}
