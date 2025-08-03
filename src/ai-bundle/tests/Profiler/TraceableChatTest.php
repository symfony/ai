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
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Chat;
use Symfony\AI\Agent\Chat\MessageStore\InMemoryStore;
use Symfony\AI\AiBundle\Profiler\TraceableChat;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Result\TextResult;

#[CoversClass(TraceableChat::class)]
#[UsesClass(InMemoryStore::class)]
#[UsesClass(Chat::class)]
#[UsesClass(Message::class)]
#[UsesClass(TextResult::class)]
final class TraceableChatTest extends TestCase
{
    public function testIdCanBeRetrieved()
    {
        $agent = $this->createMock(AgentInterface::class);

        $store = new InMemoryStore('foo');
        $chat = new Chat($agent, $store);

        $traceableChat = new TraceableChat($chat);

        $this->assertSame('foo', $traceableChat->getId());
    }

    public function testCurrentMessageBagCanBeRetrieved()
    {
        $agent = $this->createMock(AgentInterface::class);
        $agent->expects($this->once())->method('call')->willReturn(new TextResult('foo'));

        $store = new InMemoryStore('foo');
        $chat = new Chat($agent, $store);

        $traceableChat = new TraceableChat($chat);
        $traceableChat->submit(Message::ofUser('foo'));

        $this->assertCount(2, $traceableChat->getCurrentMessageBag());
    }

    public function testSpecificMessageBagCanBeRetrieved()
    {
        $agent = $this->createMock(AgentInterface::class);
        $agent->expects($this->once())->method('call')->willReturn(new TextResult('foo'));

        $store = new InMemoryStore('foo');
        $chat = new Chat($agent, $store);

        $traceableChat = new TraceableChat($chat);
        $traceableChat->submit(Message::ofUser('foo'));

        $this->assertCount(2, $traceableChat->getCurrentMessageBag());
        $this->assertCount(0, $traceableChat->getMessageBag('bar'));
    }
}
