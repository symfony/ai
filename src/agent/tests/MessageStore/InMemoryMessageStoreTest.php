<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\MessageStore;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Exception\ConflictException;
use Symfony\AI\Agent\MessageStore\InMemoryMessageStore;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Uid\Uuid;

final class InMemoryMessageStoreTest extends TestCase
{
    public function testLoadReturnsAnEmptyBagWithTheIdForUnknownMessages()
    {
        $id = Uuid::v7();

        $messages = (new InMemoryMessageStore())->load($id);

        $this->assertCount(0, $messages);
        $this->assertTrue($id->equals($messages->getId()));
    }

    public function testSavedMessagesAreLoadedById()
    {
        $store = new InMemoryMessageStore();
        $messages = new MessageBag(Message::ofUser('Hello'));
        $other = new MessageBag(Message::ofUser('Hi'));

        $store->save($messages);
        $store->save($other);

        $this->assertSame($messages, $store->load($messages->getId()));
        $this->assertSame($other, $store->load($other->getId()));
    }

    public function testSaveAcceptsMessagesContinuingTheStoredOnes()
    {
        $store = new InMemoryMessageStore();
        $messages = new MessageBag(Message::ofUser('Hello'));
        $store->save($messages);

        $store->save($continued = $messages->with(Message::ofAssistant('Hi')));

        $this->assertSame($continued, $store->load($messages->getId()));
    }

    public function testSaveRejectsMessagesNotContinuingTheStoredOnes()
    {
        $store = new InMemoryMessageStore();
        $messages = new MessageBag(Message::ofUser('Hello'));
        $store->save($messages->with(Message::ofAssistant('Hi')));

        $this->expectException(ConflictException::class);

        $store->save($messages->with(Message::ofAssistant('Hey')));
    }

    public function testSaveRejectsMessagesOlderThanTheStoredOnes()
    {
        $store = new InMemoryMessageStore();
        $messages = new MessageBag(Message::ofUser('Hello'));
        $store->save($messages->with(Message::ofAssistant('Hi')));

        $this->expectException(ConflictException::class);

        $store->save($messages);
    }
}
