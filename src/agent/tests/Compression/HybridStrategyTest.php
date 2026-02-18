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
use Symfony\AI\Agent\Compression\CompressionStrategyInterface;
use Symfony\AI\Agent\Compression\HybridStrategy;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

final class HybridStrategyTest extends TestCase
{
    public function testShouldCompressReturnsFalseWhenBelowPrimaryThreshold()
    {
        $primary = $this->createMock(CompressionStrategyInterface::class);
        $secondary = $this->createMock(CompressionStrategyInterface::class);

        $strategy = new HybridStrategy($primary, $secondary, primaryThreshold: 5, secondaryThreshold: 10);

        $messages = new MessageBag(
            Message::ofUser('Message 1'),
            Message::ofAssistant('Response 1'),
        );

        $this->assertFalse($strategy->shouldCompress($messages));
    }

    public function testShouldCompressReturnsTrueWhenAbovePrimaryThreshold()
    {
        $primary = $this->createMock(CompressionStrategyInterface::class);
        $secondary = $this->createMock(CompressionStrategyInterface::class);

        $strategy = new HybridStrategy($primary, $secondary, primaryThreshold: 2, secondaryThreshold: 10);

        $messages = new MessageBag(
            Message::ofUser('Message 1'),
            Message::ofAssistant('Response 1'),
            Message::ofUser('Message 2'),
        );

        $this->assertTrue($strategy->shouldCompress($messages));
    }

    public function testCompressUsesPrimaryStrategyWhenBelowSecondaryThreshold()
    {
        $messages = new MessageBag(
            Message::ofUser('Message 1'),
            Message::ofAssistant('Response 1'),
            Message::ofUser('Message 2'),
        );

        $compressedBag = new MessageBag(Message::ofUser('Compressed'));

        $primary = $this->createMock(CompressionStrategyInterface::class);
        $primary->expects($this->once())
            ->method('compress')
            ->with($messages)
            ->willReturn($compressedBag);

        $secondary = $this->createMock(CompressionStrategyInterface::class);
        $secondary->expects($this->never())->method('compress');

        $strategy = new HybridStrategy($primary, $secondary, primaryThreshold: 2, secondaryThreshold: 10);

        $result = $strategy->compress($messages);

        $this->assertSame($compressedBag, $result);
    }

    public function testCompressUsesSecondaryStrategyWhenAboveSecondaryThreshold()
    {
        $messageList = [
            Message::ofUser('Message 1'),
            Message::ofAssistant('Response 1'),
        ];

        for ($i = 2; $i <= 6; ++$i) {
            $messageList[] = Message::ofUser("Message {$i}");
            $messageList[] = Message::ofAssistant("Response {$i}");
        }

        $messages = new MessageBag(...$messageList);

        $compressedBag = new MessageBag(Message::ofUser('Summarized'));

        $primary = $this->createMock(CompressionStrategyInterface::class);
        $primary->expects($this->never())->method('compress');

        $secondary = $this->createMock(CompressionStrategyInterface::class);
        $secondary->expects($this->once())
            ->method('compress')
            ->with($messages)
            ->willReturn($compressedBag);

        $strategy = new HybridStrategy($primary, $secondary, primaryThreshold: 2, secondaryThreshold: 5);

        $result = $strategy->compress($messages);

        $this->assertSame($compressedBag, $result);
    }

    public function testShouldCompressIgnoresSystemMessages()
    {
        $primary = $this->createMock(CompressionStrategyInterface::class);
        $secondary = $this->createMock(CompressionStrategyInterface::class);

        $strategy = new HybridStrategy($primary, $secondary, primaryThreshold: 3, secondaryThreshold: 10);

        $messages = new MessageBag(
            Message::forSystem('System prompt'),
            Message::ofUser('Message 1'),
            Message::ofAssistant('Response 1'),
        );

        $this->assertFalse($strategy->shouldCompress($messages));
    }
}
