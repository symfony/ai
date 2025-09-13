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
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\Speech\Speech;
use Symfony\AI\Platform\Speech\SpeechListenerInterface;
use Symfony\AI\Platform\Speech\SpeechProviderInterface;
use Symfony\AI\Platform\Speech\SpeechProviderListener;

final class SpeechProviderListenerTest extends TestCase
{
    public function testListenerIsConfigured()
    {
        $this->assertArrayHasKey(InvocationEvent::class, SpeechProviderListener::getSubscribedEvents());
        $this->assertArrayHasKey(ResultEvent::class, SpeechProviderListener::getSubscribedEvents());
        $this->assertSame(['onInvocation', 255], SpeechProviderListener::getSubscribedEvents()[InvocationEvent::class]);
        $this->assertSame('onResult', SpeechProviderListener::getSubscribedEvents()[ResultEvent::class]);
    }

    public function testListenerCannotBeTriggeredWithoutSupporting()
    {
        $speechListener = $this->createMock(SpeechListenerInterface::class);
        $speechListener->expects($this->once())->method('support')->willReturn(false);
        $speechListener->expects($this->never())->method('listen');

        $listener = new SpeechProviderListener([], [
            $speechListener,
        ]);

        $event = new InvocationEvent(new ElevenLabs('foo'), []);

        $listener->onInvocation($event);
    }

    public function testListenerCanBeTriggeredWhenSupporting()
    {
        $speechListener = $this->createMock(SpeechListenerInterface::class);
        $speechListener->expects($this->once())->method('support')->willReturn(true);
        $speechListener->expects($this->once())->method('listen')->willReturn(new Text('foo'));

        $listener = new SpeechProviderListener([], [
            $speechListener,
        ]);

        $event = new InvocationEvent(new ElevenLabs('foo'), []);

        $listener->onInvocation($event);

        $this->assertInstanceOf(Text::class, $event->getInput());
    }

    public function testListenerCanBeTriggeredWhenSupportingWithMessageBag()
    {
        $speechListener = $this->createMock(SpeechListenerInterface::class);
        $speechListener->expects($this->once())->method('support')->willReturn(true);
        $speechListener->expects($this->once())->method('listen')->willReturn(new Text('foo'));

        $listener = new SpeechProviderListener([], [
            $speechListener,
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

        $speechProvider = $this->createMock(SpeechProviderInterface::class);
        $speechProvider->expects($this->once())->method('support')->willReturn(false);
        $speechProvider->expects($this->never())->method('generate');

        $listener = new SpeechProviderListener([
            $speechProvider,
        ], []);

        $event = new ResultEvent(new ElevenLabs('foo'), $deferredResult);

        $listener->onResult($event);
    }

    public function testProviderCanBeTriggeredWhenSupporting()
    {
        $rawResult = $this->createMock(RawResultInterface::class);
        $resultConverter = $this->createMock(ResultConverterInterface::class);

        $deferredResult = new DeferredResult($resultConverter, $rawResult);
        $speechDeferredResult = new DeferredResult($resultConverter, $rawResult);

        $speech = new Speech([], $speechDeferredResult, 'foo');

        $speechProvider = $this->createMock(SpeechProviderInterface::class);
        $speechProvider->expects($this->once())->method('support')->willReturn(true);
        $speechProvider->expects($this->once())->method('generate')->willReturn($speech);

        $listener = new SpeechProviderListener([
            $speechProvider,
        ], []);

        $event = new ResultEvent(new ElevenLabs('foo'), $deferredResult);

        $listener->onResult($event);

        $this->assertSame($deferredResult, $event->getDeferredResult());
        $this->assertSame($speech, $event->getDeferredResult()->getSpeech('foo'));
    }
}
