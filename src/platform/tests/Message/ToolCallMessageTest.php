<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Message;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tests\Helper\UuidAssertionTrait;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;
use Symfony\Component\Uid\UuidV7;

final class ToolCallMessageTest extends TestCase
{
    use UuidAssertionTrait;

    public function testConstructionIsPossible()
    {
        $toolCall = new ToolCall('foo', 'bar');
        $obj = new ToolCallMessage($toolCall, 'bar');

        $this->assertSame($toolCall, $obj->getToolCall());
        $this->assertSame('bar', $obj->getContent());
    }

    public function testMessageHasUid()
    {
        $toolCall = new ToolCall('foo', 'bar');
        $message = new ToolCallMessage($toolCall, 'bar');

        $this->assertInstanceOf(UuidV7::class, $message->getId());
    }

    public function testDifferentMessagesHaveDifferentUids()
    {
        $toolCall = new ToolCall('foo', 'bar');
        $message1 = new ToolCallMessage($toolCall, 'bar');
        $message2 = new ToolCallMessage($toolCall, 'baz');

        $this->assertNotSame($message1->getId()->toRfc4122(), $message2->getId()->toRfc4122());
        $this->assertIsUuidV7($message1->getId()->toRfc4122());
        $this->assertIsUuidV7($message2->getId()->toRfc4122());
    }

    public function testSameMessagesHaveDifferentUids()
    {
        $toolCall = new ToolCall('foo', 'bar');
        $message1 = new ToolCallMessage($toolCall, 'bar');
        $message2 = new ToolCallMessage($toolCall, 'bar');

        $this->assertNotSame($message1->getId()->toRfc4122(), $message2->getId()->toRfc4122());
        $this->assertIsUuidV7($message1->getId()->toRfc4122());
        $this->assertIsUuidV7($message2->getId()->toRfc4122());
    }

    public function testMessageIdImplementsRequiredInterfaces()
    {
        $toolCall = new ToolCall('foo', 'bar');
        $message = new ToolCallMessage($toolCall, 'test');

        $this->assertInstanceOf(AbstractUid::class, $message->getId());
        $this->assertInstanceOf(TimeBasedUidInterface::class, $message->getId());
        $this->assertInstanceOf(UuidV7::class, $message->getId());
    }
}
