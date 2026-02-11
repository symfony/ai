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
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\MockAgent;
use Symfony\AI\AiBundle\Profiler\TraceableChat;
use Symfony\AI\Chat\Chat;
use Symfony\AI\Chat\InMemory\Store as InMemoryStore;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\Component\Clock\MonotonicClock;

final class TraceableChatTest extends TestCase
{
    public function testDataCanBeCollected()
    {
        $chat = new Chat(new MockAgent([
            'Hello World' => 'General Kenobi',
            'Second Hello world' => 'General Kenobi',
            'Third Hello world' => 'General Kenobi',
        ]), new InMemoryStore());

        $traceableChat = new TraceableChat($chat, new MonotonicClock());

        $this->assertCount(0, $traceableChat->calls);

        $traceableChat->initiate(new MessageBag(
            Message::ofUser('Hello World'),
        ));

        $this->assertCount(1, $traceableChat->calls);

        $this->assertArrayHasKey('action', $traceableChat->calls[0]);
        $this->assertArrayHasKey('bag', $traceableChat->calls[0]);
        $this->assertArrayHasKey('initiated_at', $traceableChat->calls[0]);
        $this->assertSame('initiate', $traceableChat->calls[0]['action']);
        $this->assertInstanceOf(MessageBag::class, $traceableChat->calls[0]['bag']);
        $this->assertCount(1, $traceableChat->calls[0]['bag']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $traceableChat->calls[0]['initiated_at']);

        $traceableChat->submit(Message::ofUser('Second Hello world'));

        $this->assertCount(2, $traceableChat->calls);

        $this->assertArrayHasKey('action', $traceableChat->calls[1]);
        $this->assertArrayHasKey('message', $traceableChat->calls[1]);
        $this->assertArrayHasKey('submitted_at', $traceableChat->calls[1]);
        $this->assertSame('submit', $traceableChat->calls[1]['action']);
        $this->assertInstanceOf(UserMessage::class, $traceableChat->calls[1]['message']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $traceableChat->calls[1]['submitted_at']);

        $traceableChat->stream(Message::ofUser('Third Hello world'));

        $this->assertCount(3, $traceableChat->calls);

        $this->assertArrayHasKey('action', $traceableChat->calls[2]);
        $this->assertArrayHasKey('message', $traceableChat->calls[2]);
        $this->assertArrayHasKey('streamed_at', $traceableChat->calls[2]);
        $this->assertSame('stream', $traceableChat->calls[2]['action']);
        $this->assertInstanceOf(UserMessage::class, $traceableChat->calls[2]['message']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $traceableChat->calls[2]['streamed_at']);
    }

    public function testResetClearsCalls()
    {
        $agent = $this->createStub(AgentInterface::class);
        $chat = new Chat($agent, new InMemoryStore());
        $traceableChat = new TraceableChat($chat, new MonotonicClock());

        $traceableChat->initiate(new MessageBag());
        $this->assertCount(1, $traceableChat->calls);

        $traceableChat->reset();
        $this->assertCount(0, $traceableChat->calls);
    }
}
