<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Tests\Profiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Chat\MessageStore\InMemoryStore;
use Symfony\AI\AiBundle\Profiler\TraceableMessageStore;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

#[CoversClass(TraceableMessageStore::class)]
#[UsesClass(TraceableMessageStore::class)]
#[UsesClass(InMemoryStore::class)]
#[UsesClass(MessageBag::class)]
#[UsesClass(Message::class)]
final class TraceableMessageStoreTest extends TestCase
{
    public function testStoreIsConfigured()
    {
        $messageStore = new InMemoryStore();

        $traceableMessageStore = new TraceableMessageStore($messageStore);

        $this->assertSame('_message_store_memory', $traceableMessageStore->getId());
    }

    public function testMessagesCanBeSaved()
    {
        $messageStore = new InMemoryStore();

        $traceableMessageStore = new TraceableMessageStore($messageStore);
        $traceableMessageStore->save(new MessageBag(
            Message::ofUser('foo'),
        ));

        $this->assertArrayHasKey('_message_store_memory', $traceableMessageStore->messages);
        $this->assertCount(1, $traceableMessageStore->messages['_message_store_memory']);

        $traceableMessageStore->save(new MessageBag(
            Message::ofUser('bar'),
        ), 'bar');

        $this->assertArrayHasKey('bar', $traceableMessageStore->messages);
        $this->assertCount(1, $traceableMessageStore->messages['bar']);
    }

    public function testMessagesCanBeLoaded()
    {
        $messageStore = new InMemoryStore();

        $traceableMessageStore = new TraceableMessageStore($messageStore);
        $traceableMessageStore->save(new MessageBag(
            Message::ofUser('foo'),
        ));

        $this->assertCount(1, $traceableMessageStore->load());
    }

    public function testMessagesCanBeCleared()
    {
        $messageStore = new InMemoryStore();

        $traceableMessageStore = new TraceableMessageStore($messageStore);
        $traceableMessageStore->save(new MessageBag(
            Message::ofUser('foo'),
        ));

        $this->assertCount(1, $traceableMessageStore->load());

        $traceableMessageStore->clear();

        $this->assertCount(0, $traceableMessageStore->load());
    }
}
