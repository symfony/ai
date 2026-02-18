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
use Symfony\AI\Agent\Compression\Event\BeforeContextCompression;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

final class BeforeContextCompressionTest extends TestCase
{
    public function testGetOriginalMessages()
    {
        $messages = new MessageBag(Message::ofUser('Hello'));
        $event = new BeforeContextCompression($messages);

        $this->assertSame($messages, $event->getOriginalMessages());
    }

    public function testIsNotSkippedByDefault()
    {
        $messages = new MessageBag(Message::ofUser('Hello'));
        $event = new BeforeContextCompression($messages);

        $this->assertFalse($event->isSkipped());
    }

    public function testSkipMarksEventAsSkipped()
    {
        $messages = new MessageBag(Message::ofUser('Hello'));
        $event = new BeforeContextCompression($messages);

        $event->skip();

        $this->assertTrue($event->isSkipped());
    }
}
