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
use Symfony\AI\AiBundle\Profiler\DataCollector;
use Symfony\AI\AiBundle\Profiler\TraceableChat;
use Symfony\AI\AiBundle\Profiler\TraceableMessageStore;
use Symfony\AI\AiBundle\Profiler\TraceablePlatform;
use Symfony\AI\Chat\Chat;
use Symfony\AI\Chat\InMemory\Store as InMemoryStore;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Test\PlainConverter;
use Symfony\Component\Clock\MonotonicClock;

class DataCollectorTest extends TestCase
{
    public function testCollectsDataForNonStreamingResponse()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $traceablePlatform = new TraceablePlatform($platform);
        $messageBag = new MessageBag(Message::ofUser(new Text('Hello')));
        $result = new TextResult('Assistant response');

        $platform->method('invoke')->willReturn(new DeferredResult(new PlainConverter($result), $this->createStub(RawResultInterface::class)));

        $result = $traceablePlatform->invoke('gpt-4o', $messageBag, ['stream' => false]);
        $this->assertSame('Assistant response', $result->asText());

        $dataCollector = new DataCollector([$traceablePlatform], [], [], []);
        $dataCollector->lateCollect();

        $this->assertCount(1, $dataCollector->getPlatformCalls());
        $this->assertSame('Assistant response', $dataCollector->getPlatformCalls()[0]['result']);
    }

    public function testCollectsDataForStreamingResponse()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $traceablePlatform = new TraceablePlatform($platform);
        $messageBag = new MessageBag(Message::ofUser(new Text('Hello')));
        $result = new StreamResult(
            (function () {
                yield 'Assistant ';
                yield 'response';
            })(),
        );

        $platform->method('invoke')->willReturn(new DeferredResult(new PlainConverter($result), $this->createStub(RawResultInterface::class)));

        $result = $traceablePlatform->invoke('gpt-4o', $messageBag, ['stream' => true]);
        $this->assertSame('Assistant response', implode('', iterator_to_array($result->asStream())));

        $dataCollector = new DataCollector([$traceablePlatform], [], [], []);
        $dataCollector->lateCollect();

        $this->assertCount(1, $dataCollector->getPlatformCalls());
        $this->assertSame('Assistant response', $dataCollector->getPlatformCalls()[0]['result']);
    }

    public function testCollectsDataForUnconsumedStreamingResponse()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $traceablePlatform = new TraceablePlatform($platform);
        $messageBag = new MessageBag(Message::ofUser(new Text('Hello')));
        $result = new StreamResult(
            (function () {
                yield 'Assistant ';
                yield 'response';
            })(),
        );

        $platform->method('invoke')->willReturn(new DeferredResult(new PlainConverter($result), $this->createStub(RawResultInterface::class)));

        // Invoke but do NOT consume the stream
        $traceablePlatform->invoke('gpt-4o', $messageBag, ['stream' => true]);

        $dataCollector = new DataCollector([$traceablePlatform], [], [], []);
        $dataCollector->lateCollect();

        $this->assertCount(1, $dataCollector->getPlatformCalls());
        $this->assertNull($dataCollector->getPlatformCalls()[0]['result']);
    }

    public function testCollectsDataForMessageStore()
    {
        $traceableMessageStore = new TraceableMessageStore(new InMemoryStore(), new MonotonicClock());
        $traceableMessageStore->save(new MessageBag(
            Message::ofUser('Hello World'),
        ));

        $dataCollector = new DataCollector([], [], [$traceableMessageStore], []);
        $dataCollector->lateCollect();

        $calls = $dataCollector->getMessages();

        $this->assertArrayHasKey('bag', $calls[0]);
        $this->assertArrayHasKey('saved_at', $calls[0]);
        $this->assertInstanceOf(MessageBag::class, $calls[0]['bag']);
        $this->assertCount(1, $calls[0]['bag']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $calls[0]['saved_at']);
    }

    public function testCollectsDataForChat()
    {
        $agent = $this->createMock(AgentInterface::class);
        $agent->expects($this->once())->method('call')->willReturn(new TextResult('foo'));

        $chat = new Chat($agent, new InMemoryStore());

        $traceableChat = new TraceableChat($chat, new MonotonicClock());

        $traceableChat->submit(Message::ofUser('Hello World'));

        $dataCollector = new DataCollector([], [], [], [$traceableChat]);
        $dataCollector->lateCollect();

        $calls = $dataCollector->getChats();

        $this->assertArrayHasKey('message', $calls[0]);
        $this->assertArrayHasKey('saved_at', $calls[0]);
        $this->assertInstanceOf(UserMessage::class, $calls[0]['message']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $calls[0]['saved_at']);
    }
}
