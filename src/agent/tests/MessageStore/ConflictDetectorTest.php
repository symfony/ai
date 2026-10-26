<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\MessageStore;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Exception\ConflictException;
use Symfony\AI\Agent\MessageStore\ConflictDetector;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

final class ConflictDetectorTest extends TestCase
{
    public function testMessagesContinuingTheStoredOnesPass()
    {
        $stored = new MessageBag(Message::ofUser('Hello'));

        ConflictDetector::assertContinues($stored, $stored->with(Message::ofAssistant('Hi')));
        ConflictDetector::assertContinues(new MessageBag(), $stored);

        $this->addToAssertionCount(2);
    }

    public function testMessagesDivergingFromTheStoredOnesConflict()
    {
        $stored = new MessageBag(Message::ofUser('Hello'), Message::ofAssistant('Hi'));

        $this->expectException(ConflictException::class);

        ConflictDetector::assertContinues($stored, new MessageBag(Message::ofUser('Hello')));
    }

    public function testVersionIsTheIdOfTheLastMessage()
    {
        $answer = Message::ofAssistant('Hi');

        $this->assertSame('', ConflictDetector::version(new MessageBag()));
        $this->assertSame($answer->getId()->toRfc4122(), ConflictDetector::version(new MessageBag(Message::ofUser('Hello'), $answer)));
    }
}
