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
use Symfony\AI\Agent\Compression\SlidingWindowStrategy;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\UserMessage;

final class SlidingWindowStrategyTest extends TestCase
{
    public function testShouldCompressReturnsFalseWhenBelowThreshold()
    {
        $strategy = new SlidingWindowStrategy(max: 5, threshold: 10);

        $messages = new MessageBag(
            Message::ofUser('Message 1'),
            Message::ofAssistant('Response 1'),
        );

        $this->assertFalse($strategy->shouldCompress($messages));
    }

    public function testShouldCompressReturnsTrueWhenAboveThreshold()
    {
        $strategy = new SlidingWindowStrategy(max: 5, threshold: 3);

        $messages = new MessageBag(
            Message::ofUser('Message 1'),
            Message::ofAssistant('Response 1'),
            Message::ofUser('Message 2'),
            Message::ofAssistant('Response 2'),
        );

        $this->assertTrue($strategy->shouldCompress($messages));
    }

    public function testShouldCompressIgnoresSystemMessages()
    {
        $strategy = new SlidingWindowStrategy(max: 5, threshold: 3);

        $messages = new MessageBag(
            Message::forSystem('System prompt'),
            Message::ofUser('Message 1'),
            Message::ofAssistant('Response 1'),
        );

        $this->assertFalse($strategy->shouldCompress($messages));
    }

    public function testCompressKeepsOnlyRecentMessages()
    {
        $strategy = new SlidingWindowStrategy(max: 2, threshold: 3);

        $messages = new MessageBag(
            Message::ofUser('Old message 1'),
            Message::ofAssistant('Old response 1'),
            Message::ofUser('Recent message'),
            Message::ofAssistant('Recent response'),
        );

        $compressed = $strategy->compress($messages);

        $this->assertCount(2, $compressed);
        $this->assertInstanceOf(UserMessage::class, $msg1 = $compressed->getMessages()[0]);
        $this->assertInstanceOf(Text::class, $text = $msg1->getContent()[0]);
        $this->assertSame('Recent message', $text->getText());
        $this->assertInstanceOf(AssistantMessage::class, $msg2 = $compressed->getMessages()[1]);
        $this->assertSame('Recent response', $msg2->getContent());
    }

    public function testCompressPreservesSystemMessage()
    {
        $strategy = new SlidingWindowStrategy(max: 2, threshold: 3);

        $messages = new MessageBag(
            Message::forSystem('System prompt'),
            Message::ofUser('Old message'),
            Message::ofAssistant('Old response'),
            Message::ofUser('Recent message'),
            Message::ofAssistant('Recent response'),
        );

        $compressed = $strategy->compress($messages);

        $this->assertCount(3, $compressed);
        $this->assertInstanceOf(SystemMessage::class, $msg1 = $compressed->getMessages()[0]);
        $this->assertSame('System prompt', $msg1->getContent());
        $this->assertInstanceOf(UserMessage::class, $msg2 = $compressed->getMessages()[1]);
        $this->assertInstanceOf(Text::class, $text = $msg2->getContent()[0]);
        $this->assertSame('Recent message', $text->getText());
        $this->assertInstanceOf(AssistantMessage::class, $msg3 = $compressed->getMessages()[2]);
        $this->assertSame('Recent response', $msg3->getContent());
    }
}
