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
use Symfony\AI\Agent\MessageStore\InMemoryMessageStore;
use Symfony\AI\Agent\MessageStore\TraceableMessageStore;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\MonotonicClock;

final class TraceableMessageStoreTest extends TestCase
{
    public function testSavesAreTracedAndReset()
    {
        $clock = new MockClock('2026-10-25 12:00:00');
        $store = new TraceableMessageStore($inner = new InMemoryMessageStore(), $clock);
        $messages = new MessageBag(Message::ofUser('Hello'));

        $store->save($messages);

        $this->assertEquals($messages, $store->load($messages->getId()));
        $this->assertEquals([['bag' => $messages, 'saved_at' => $clock->now()]], $store->getCalls());

        $store->reset();

        $this->assertSame([], $store->getCalls());
        $this->assertCount(0, $inner->load($messages->getId()));
    }

    public function testDeleteIsForwardedToTheDecoratedStore()
    {
        $store = new InMemoryMessageStore();
        $messages = new MessageBag(Message::ofUser('Hello'));
        $store->save($messages);

        (new TraceableMessageStore($store, new MonotonicClock()))->delete($messages->getId());

        $this->assertCount(0, $store->load($messages->getId()));
    }
}
