<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Compression\Event;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Compression\Event\AfterContextCompression;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

final class AfterContextCompressionTest extends TestCase
{
    public function testGetOriginalMessages()
    {
        $original = new MessageBag(
            Message::ofUser('Message 1'),
            Message::ofAssistant('Response 1'),
        );
        $compressed = new MessageBag(Message::ofUser('Compressed'));

        $event = new AfterContextCompression($original, $compressed);

        $this->assertSame($original, $event->getOriginalMessages());
    }

    public function testGetCompressedMessages()
    {
        $original = new MessageBag(
            Message::ofUser('Message 1'),
            Message::ofAssistant('Response 1'),
        );
        $compressed = new MessageBag(Message::ofUser('Compressed'));

        $event = new AfterContextCompression($original, $compressed);

        $this->assertSame($compressed, $event->getCompressedMessages());
    }

    public function testSetCompressedMessages()
    {
        $original = new MessageBag(
            Message::ofUser('Message 1'),
            Message::ofAssistant('Response 1'),
        );
        $compressed = new MessageBag(Message::ofUser('Compressed'));
        $modified = new MessageBag(Message::ofUser('Modified'));

        $event = new AfterContextCompression($original, $compressed);
        $event->setCompressedMessages($modified);

        $this->assertSame($modified, $event->getCompressedMessages());
    }

    public function testGetCompressionDelta()
    {
        $original = new MessageBag(
            Message::ofUser('Message 1'),
            Message::ofAssistant('Response 1'),
            Message::ofUser('Message 2'),
            Message::ofAssistant('Response 2'),
        );
        $compressed = new MessageBag(Message::ofUser('Compressed'));

        $event = new AfterContextCompression($original, $compressed);

        $this->assertSame(3, $event->getCompressionDelta());
    }
}
