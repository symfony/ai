<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\MockAgent;
use Symfony\AI\Chat\Chat;
use Symfony\AI\Chat\InMemory\Store as InMemoryStore;
use Symfony\AI\Chat\TraceableChat;
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

        $this->assertCount(0, $traceableChat->getCalls());

        $traceableChat->initiate(new MessageBag(
            Message::ofUser('Hello World'),
        ));

        $calls = $traceableChat->getCalls();
        $this->assertCount(1, $calls);

        $this->assertArrayHasKey('action', $calls[0]);
        $this->assertArrayHasKey('bag', $calls[0]);
        $this->assertArrayHasKey('initiated_at', $calls[0]);
        $this->assertSame('initiate', $calls[0]['action']);
        $this->assertInstanceOf(MessageBag::class, $calls[0]['bag']);
        $this->assertCount(1, $calls[0]['bag']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $calls[0]['initiated_at']);

        $traceableChat->submit(Message::ofUser('Second Hello world'));

        $calls = $traceableChat->getCalls();
        $this->assertCount(2, $calls);

        $this->assertArrayHasKey('action', $calls[1]);
        $this->assertArrayHasKey('message', $calls[1]);
        $this->assertArrayHasKey('submitted_at', $calls[1]);
        $this->assertSame('submit', $calls[1]['action']);
        $this->assertInstanceOf(UserMessage::class, $calls[1]['message']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $calls[1]['submitted_at']);

        $traceableChat->stream(Message::ofUser('Third Hello world'));

        $calls = $traceableChat->getCalls();
        $this->assertCount(3, $calls);

        $this->assertArrayHasKey('action', $calls[2]);
        $this->assertArrayHasKey('message', $calls[2]);
        $this->assertArrayHasKey('streamed_at', $calls[2]);
        $this->assertSame('stream', $calls[2]['action']);
        $this->assertInstanceOf(UserMessage::class, $calls[2]['message']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $calls[2]['streamed_at']);
    }

    public function testResetClearsCalls()
    {
        $agent = $this->createStub(AgentInterface::class);
        $chat = new Chat($agent, new InMemoryStore());
        $traceableChat = new TraceableChat($chat, new MonotonicClock());

        $traceableChat->initiate(new MessageBag());
        $this->assertCount(1, $traceableChat->getCalls());

        $traceableChat->reset();
        $this->assertCount(0, $traceableChat->getCalls());
    }
}
