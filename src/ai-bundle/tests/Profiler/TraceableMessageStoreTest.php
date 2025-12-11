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

use PHPUnit\Framework\TestCase;
use Symfony\AI\AiBundle\Profiler\TraceableMessageStore;
use Symfony\AI\Chat\InMemory\Store as InMemoryStore;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Clock\MonotonicClock;

final class TraceableMessageStoreTest extends TestCase
{
    public function testSubmittedMessageBagCanBeRetrieved()
    {
        $messageStore = new InMemoryStore();

        $traceableMessageStore = new TraceableMessageStore($messageStore, new MonotonicClock());

        $this->assertCount(0, $traceableMessageStore->calls);

        $traceableMessageStore->save(new MessageBag(
            Message::ofUser('Hello World'),
        ));

        $this->assertCount(1, $traceableMessageStore->calls);

        $calls = $traceableMessageStore->calls;

        $this->assertArrayHasKey('bag', $calls[0]);
        $this->assertArrayHasKey('saved_at', $calls[0]);
        $this->assertInstanceOf(MessageBag::class, $calls[0]['bag']);
        $this->assertCount(1, $calls[0]['bag']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $calls[0]['saved_at']);
    }
}
