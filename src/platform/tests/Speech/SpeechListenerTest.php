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
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlainConverter;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Speech\Speech;
use Symfony\AI\Platform\Speech\SpeechConfiguration;
use Symfony\AI\Platform\Speech\SpeechListener;

final class SpeechListenerTest extends TestCase
{
    public function testListenerIsConfigured()
    {
        $this->assertArrayHasKey(InvocationEvent::class, SpeechListener::getSubscribedEvents());
        $this->assertArrayHasKey(ResultEvent::class, SpeechListener::getSubscribedEvents());
        $this->assertSame(['onInvocation', 255], SpeechListener::getSubscribedEvents()[InvocationEvent::class]);
        $this->assertSame('onResult', SpeechListener::getSubscribedEvents()[ResultEvent::class]);
    }

    public function testListenerCannotBeTriggeredOnInvocationWithoutMessageBag()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->never())->method('invoke');

        $configuration = new SpeechConfiguration([]);

        $listener = new SpeechListener([
            $platform,
        ], [
            $configuration,
        ]);

        $listener->onInvocation(new InvocationEvent(new ElevenLabs('foo'), []));
    }

    public function testListenerCannotBeTriggeredOnInvocationWithoutUserMessage()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->never())->method('invoke');

        $configuration = new SpeechConfiguration([]);

        $listener = new SpeechListener([
            $platform,
        ], [
            $configuration,
        ]);

        $listener->onInvocation(new InvocationEvent(new Model('foo'), new MessageBag()));
    }

    public function testListenerCannotBeTriggeredOnInvocationWithoutUserMessageWithAudio()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->never())->method('invoke');

        $configuration = new SpeechConfiguration([]);

        $listener = new SpeechListener([
            $platform,
        ], [
            $configuration,
        ]);

        $listener->onInvocation(new InvocationEvent(new Model('foo'), new MessageBag(
            Message::ofUser('Hello world'),
        )));
    }

    public function testListenerCannotBeTriggeredOnInvocationWithoutSupportingConfiguration()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->never())->method('invoke');

        $configuration = new SpeechConfiguration([
            'tts_model' => 'foo',
        ]);

        $listener = new SpeechListener([
            $platform,
        ], [
            $configuration,
        ]);

        $listener->onInvocation(new InvocationEvent(new Model('foo'), new MessageBag(
            Message::ofUser(Audio::fromFile(\dirname(__DIR__, 4).'/fixtures/audio.mp3')),
        )));
    }

    public function testListenerCannotBeTriggeredOnInvocationWithoutValidPlatform()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->never())->method('invoke');

        $configuration = new SpeechConfiguration([
            'stt_model' => 'foo',
        ]);

        $listener = new SpeechListener([
            'foo' => $platform,
        ], [
            'bar' => $configuration,
        ]);

        $event = new InvocationEvent(new Model('foo'), new MessageBag(
            Message::ofUser(Audio::fromFile(\dirname(__DIR__, 4).'/fixtures/audio.mp3')),
        ));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No platform found for configuration "bar".');
        $this->expectExceptionCode(0);
        $listener->onInvocation($event);
    }

    public function testListenerCanBeTriggeredOnInvocation()
    {
        $speechResult = new DeferredResult(new PlainConverter(new TextResult('foo')), new InMemoryRawResult());

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())->method('invoke')->willReturn($speechResult);

        $configuration = new SpeechConfiguration([
            'stt_model' => 'foo',
        ]);

        $listener = new SpeechListener([
            'foo' => $platform,
        ], [
            'foo' => $configuration,
        ]);

        $event = new InvocationEvent(new Model('foo'), new MessageBag(
            Message::ofUser(Audio::fromFile(\dirname(__DIR__, 4).'/fixtures/audio.mp3')),
        ));

        $listener->onInvocation($event);

        $this->assertInstanceOf(MessageBag::class, $event->getInput());
        $this->assertSame('foo', $event->getInput()->getUserMessage()->asText());
    }

    public function testListenerCannotBeTriggeredOnResultWithoutSupportingConfiguration()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->never())->method('invoke');

        $configuration = new SpeechConfiguration([
            'stt_model' => 'foo',
        ]);

        $listener = new SpeechListener([
            $platform,
        ], [
            $configuration,
        ]);

        $event = new ResultEvent(new ElevenLabs('foo'), new DeferredResult(new PlainConverter(new TextResult('foo')), new InMemoryRawResult()));

        $listener->onResult($event);
    }

    public function testListenerCannotBeTriggeredOnResultWithoutValidPlatform()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->never())->method('invoke');

        $configuration = new SpeechConfiguration([
            'tts_model' => 'foo',
        ]);

        $listener = new SpeechListener([
            'foo' => $platform,
        ], [
            'bar' => $configuration,
        ]);

        $event = new ResultEvent(new ElevenLabs('foo'), new DeferredResult(new PlainConverter(new TextResult('foo')), new InMemoryRawResult()));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No platform found for configuration "bar".');
        $this->expectExceptionCode(0);
        $listener->onResult($event);
    }

    public function testListenerCanBeTriggeredOnResult()
    {
        $speechDeferredResult = new DeferredResult(new PlainConverter(new BinaryResult('foo')), new InMemoryRawResult());

        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())->method('invoke')->willReturn($speechDeferredResult);

        $configuration = new SpeechConfiguration([
            'tts_model' => 'foo',
        ]);

        $listener = new SpeechListener([
            'foo' => $platform,
        ], [
            'foo' => $configuration,
        ]);

        $event = new ResultEvent(new ElevenLabs('foo'), new DeferredResult(new PlainConverter(new TextResult('foo')), new InMemoryRawResult()));

        $listener->onResult($event);

        $this->assertInstanceOf(Speech::class, $event->getDeferredResult()->getSpeech());
    }
}
