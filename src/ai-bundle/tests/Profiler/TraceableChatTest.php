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
use Symfony\AI\AiBundle\Profiler\TraceableChat;
use Symfony\AI\Chat\Chat;
use Symfony\AI\Chat\InMemory\Store as InMemoryStore;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\Clock\MonotonicClock;

final class TraceableChatTest extends TestCase
{
    public function testInitializationMessageBagCanBeRetrieved()
    {
        $agent = $this->createMock(AgentInterface::class);
        $agent->expects($this->once())->method('call')->willReturn(new TextResult('foo'));

        $chat = new Chat($agent, new InMemoryStore());

        $traceableChat = new TraceableChat($chat, new MonotonicClock());

        $this->assertCount(0, $traceableChat->calls);

        $traceableChat->initiate(new MessageBag(
            Message::ofUser('Hello World'),
        ));

        $this->assertCount(1, $traceableChat->calls);

        $this->assertArrayHasKey('action', $traceableChat->calls[0]);
        $this->assertArrayHasKey('bag', $traceableChat->calls[0]);
        $this->assertArrayHasKey('saved_at', $traceableChat->calls[0]);
        $this->assertSame('initiate', $traceableChat->calls[0]['action']);
        $this->assertInstanceOf(MessageBag::class, $traceableChat->calls[0]['bag']);
        $this->assertCount(1, $traceableChat->calls[0]['bag']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $traceableChat->calls[0]['saved_at']);

        $traceableChat->submit(Message::ofUser('Second Hello world'));

        $this->assertCount(2, $traceableChat->calls);

        $this->assertArrayHasKey('action', $traceableChat->calls[1]);
        $this->assertArrayHasKey('message', $traceableChat->calls[1]);
        $this->assertArrayHasKey('saved_at', $traceableChat->calls[1]);
        $this->assertSame('submit', $traceableChat->calls[1]['action']);
        $this->assertInstanceOf(UserMessage::class, $traceableChat->calls[1]['message']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $traceableChat->calls[1]['saved_at']);
    }
}
