<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Speech;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\ElevenLabs\ElevenLabs;
use Symfony\AI\Platform\Event\InvocationEvent;
use Symfony\AI\Platform\Event\ResultEvent;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\Speech\Speech;
use Symfony\AI\Platform\Speech\SpeechListener;
use Symfony\AI\Platform\Speech\SpeechPlatformInterface;

final class SpeechListenerTest extends TestCase
{
    public function testListenerIsConfigured()
    {
        $this->assertArrayHasKey(InvocationEvent::class, SpeechListener::getSubscribedEvents());
        $this->assertArrayHasKey(ResultEvent::class, SpeechListener::getSubscribedEvents());
        $this->assertSame(['onInvocation', 255], SpeechListener::getSubscribedEvents()[InvocationEvent::class]);
        $this->assertSame('onResult', SpeechListener::getSubscribedEvents()[ResultEvent::class]);
    }

    public function testListenerCannotBeTriggeredWithoutSupporting()
    {
        $speechPlatform = $this->createMock(SpeechPlatformInterface::class);
        $speechPlatform->expects($this->once())->method('listen')->willReturn(null);

        $listener = new SpeechListener([
            $speechPlatform,
        ]);

        $listener->onInvocation(new InvocationEvent(new ElevenLabs('foo'), []));
    }

    public function testListenerCanBeTriggeredWhenSupporting()
    {
        $rawResult = $this->createMock(RawResultInterface::class);

        $resultConverter = $this->createMock(ResultConverterInterface::class);
        $resultConverter->expects($this->once())->method('convert')->willReturn(new TextResult('foo'));

        $deferredResult = new DeferredResult($resultConverter, $rawResult);

        $speechPlatform = $this->createMock(SpeechPlatformInterface::class);
        $speechPlatform->expects($this->once())->method('listen')->willReturn($deferredResult);

        $listener = new SpeechListener([
            'foo' => $speechPlatform,
        ]);

        $event = new InvocationEvent(new ElevenLabs('foo'), []);

        $listener->onInvocation($event);

        $this->assertInstanceOf(Text::class, $event->getInput());
    }

    public function testListenerCanBeTriggeredWhenSupportingWithMessageBag()
    {
        $rawResult = $this->createMock(RawResultInterface::class);

        $resultConverter = $this->createMock(ResultConverterInterface::class);
        $resultConverter->expects($this->once())->method('convert')->willReturn(new TextResult('foo'));

        $deferredResult = new DeferredResult($resultConverter, $rawResult);

        $speechPlatform = $this->createMock(SpeechPlatformInterface::class);
        $speechPlatform->expects($this->once())->method('listen')->willReturn($deferredResult);

        $listener = new SpeechListener([
            'foo' => $speechPlatform,
        ]);

        $event = new InvocationEvent(new ElevenLabs('foo'), new MessageBag());

        $listener->onInvocation($event);

        $this->assertInstanceOf(MessageBag::class, $event->getInput());
        $this->assertCount(1, $event->getInput());
    }

    public function testProviderCannotBeTriggeredWithoutSupporting()
    {
        $rawResult = $this->createMock(RawResultInterface::class);
        $resultConverter = $this->createMock(ResultConverterInterface::class);

        $deferredResult = new DeferredResult($resultConverter, $rawResult);

        $speechPlatform = $this->createMock(SpeechPlatformInterface::class);
        $speechPlatform->expects($this->once())->method('generate')->willReturn(null);

        $listener = new SpeechListener([
            $speechPlatform,
        ]);

        $event = new ResultEvent(new ElevenLabs('foo'), $deferredResult);

        $listener->onResult($event);
    }

    public function testProviderCanBeTriggeredWhenSupporting()
    {
        $rawResult = $this->createMock(RawResultInterface::class);
        $resultConverter = $this->createMock(ResultConverterInterface::class);

        $deferredResult = new DeferredResult($resultConverter, $rawResult);
        $speechDeferredResult = new DeferredResult($resultConverter, $rawResult);

        $speech = new Speech($speechDeferredResult, 'foo');

        $speechPlatform = $this->createMock(SpeechPlatformInterface::class);
        $speechPlatform->expects($this->once())->method('generate')->willReturn($speechDeferredResult);

        $listener = new SpeechListener([
            'foo' => $speechPlatform,
        ]);

        $event = new ResultEvent(new ElevenLabs('foo'), $deferredResult);

        $listener->onResult($event);

        $this->assertSame($deferredResult, $event->getDeferredResult());
        $this->assertCount(1, $event->getDeferredResult()->getSpeechBag());
    }
}
