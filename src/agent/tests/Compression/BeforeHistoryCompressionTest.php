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
use Symfony\AI\Agent\Compression\BeforeHistoryCompression;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

final class BeforeHistoryCompressionTest extends TestCase
{
    public function testGetOriginalMessages()
    {
        $messages = new MessageBag(Message::ofUser('Hello'));
        $event = new BeforeHistoryCompression($messages);

        $this->assertSame($messages, $event->getOriginalMessages());
    }

    public function testIsNotSkippedByDefault()
    {
        $messages = new MessageBag(Message::ofUser('Hello'));
        $event = new BeforeHistoryCompression($messages);

        $this->assertFalse($event->isSkipped());
    }

    public function testSkipMarksEventAsSkipped()
    {
        $messages = new MessageBag(Message::ofUser('Hello'));
        $event = new BeforeHistoryCompression($messages);

        $event->skip();

        $this->assertTrue($event->isSkipped());
    }
}
