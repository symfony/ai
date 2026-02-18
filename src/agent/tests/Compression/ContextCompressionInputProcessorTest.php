<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Compression;

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\AI\Agent\Compression\CompressionStrategyInterface;
use Symfony\AI\Agent\Compression\ContextCompressionInputProcessor;
use Symfony\AI\Agent\Compression\Event\AfterContextCompression;
use Symfony\AI\Agent\Compression\Event\BeforeContextCompression;
use Symfony\AI\Agent\Input;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

final class ContextCompressionInputProcessorTest extends TestCase
{
    public function testDoesNotCompressWhenStrategyReturnsFalse()
    {
        $messages = new MessageBag(Message::ofUser('Hello'));

        $strategy = $this->createMock(CompressionStrategyInterface::class);
        $strategy->expects($this->once())
            ->method('shouldCompress')
            ->with($messages)
            ->willReturn(false);
        $strategy->expects($this->never())->method('compress');

        $processor = new ContextCompressionInputProcessor($strategy);
        $input = new Input('gpt-4', $messages);

        $processor->processInput($input);

        $this->assertSame($messages, $input->getMessageBag());
    }

    public function testCompressesWhenStrategyReturnsTrue()
    {
        $messages = new MessageBag(Message::ofUser('Hello'));
        $compressed = new MessageBag(Message::ofUser('Compressed'));

        $strategy = $this->createMock(CompressionStrategyInterface::class);
        $strategy->expects($this->once())
            ->method('shouldCompress')
            ->willReturn(true);
        $strategy->expects($this->once())
            ->method('compress')
            ->with($messages)
            ->willReturn($compressed);

        $processor = new ContextCompressionInputProcessor($strategy);
        $input = new Input('gpt-4', $messages);

        $processor->processInput($input);

        $this->assertSame($compressed, $input->getMessageBag());
    }

    public function testSkipsCompressionWhenOptionIsFalse()
    {
        $messages = new MessageBag(Message::ofUser('Hello'));

        $strategy = $this->createMock(CompressionStrategyInterface::class);
        $strategy->expects($this->never())->method('shouldCompress');
        $strategy->expects($this->never())->method('compress');

        $processor = new ContextCompressionInputProcessor($strategy);
        $input = new Input('gpt-4', $messages, ['compression' => false]);

        $processor->processInput($input);

        $this->assertSame($messages, $input->getMessageBag());
        $this->assertArrayNotHasKey('compression', $input->getOptions());
    }

    public function testRemovesCompressHistoryOptionAfterProcessing()
    {
        $messages = new MessageBag(Message::ofUser('Hello'));

        $strategy = $this->createMock(CompressionStrategyInterface::class);
        $strategy->method('shouldCompress')->willReturn(false);

        $processor = new ContextCompressionInputProcessor($strategy);
        $input = new Input('gpt-4', $messages, ['compression' => true, 'other_option' => 'value']);

        $processor->processInput($input);

        $this->assertArrayNotHasKey('compression', $input->getOptions());
        $this->assertSame('value', $input->getOptions()['other_option']);
    }

    public function testDispatchesEventsWhenCompressing()
    {
        $messages = new MessageBag(Message::ofUser('Hello'));
        $compressed = new MessageBag(Message::ofUser('Compressed'));

        $strategy = $this->createMock(CompressionStrategyInterface::class);
        $strategy->method('shouldCompress')->willReturn(true);
        $strategy->method('compress')->willReturn($compressed);

        $dispatchedEvents = [];
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function ($event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;

                return $event;
            });

        $processor = new ContextCompressionInputProcessor($strategy, $eventDispatcher);
        $input = new Input('gpt-4', $messages);

        $processor->processInput($input);

        $this->assertCount(2, $dispatchedEvents);
        $this->assertInstanceOf(BeforeContextCompression::class, $dispatchedEvents[0]);
        $this->assertInstanceOf(AfterContextCompression::class, $dispatchedEvents[1]);
    }

    public function testSkipsCompressionWhenBeforeEventIsSkipped()
    {
        $messages = new MessageBag(Message::ofUser('Hello'));

        $strategy = $this->createMock(CompressionStrategyInterface::class);
        $strategy->method('shouldCompress')->willReturn(true);
        $strategy->expects($this->never())->method('compress');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')
            ->willReturnCallback(static function ($event) {
                if ($event instanceof BeforeContextCompression) {
                    $event->skip();
                }

                return $event;
            });

        $processor = new ContextCompressionInputProcessor($strategy, $eventDispatcher);
        $input = new Input('gpt-4', $messages);

        $processor->processInput($input);

        $this->assertSame($messages, $input->getMessageBag());
    }

    public function testUsesModifiedMessagesFromAfterEvent()
    {
        $messages = new MessageBag(Message::ofUser('Hello'));
        $compressed = new MessageBag(Message::ofUser('Compressed'));
        $modified = new MessageBag(Message::ofUser('Modified by listener'));

        $strategy = $this->createMock(CompressionStrategyInterface::class);
        $strategy->method('shouldCompress')->willReturn(true);
        $strategy->method('compress')->willReturn($compressed);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')
            ->willReturnCallback(static function ($event) use ($modified) {
                if ($event instanceof AfterContextCompression) {
                    $event->setCompressedMessages($modified);
                }

                return $event;
            });

        $processor = new ContextCompressionInputProcessor($strategy, $eventDispatcher);
        $input = new Input('gpt-4', $messages);

        $processor->processInput($input);

        $this->assertSame($modified, $input->getMessageBag());
    }
}
