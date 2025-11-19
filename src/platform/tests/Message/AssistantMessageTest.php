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
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Role;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tests\Helper\UuidAssertionTrait;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;
use Symfony\Component\Uid\UuidV7;

final class AssistantMessageTest extends TestCase
{
    use UuidAssertionTrait;

    public function testTheRoleOfTheMessageIsAsExpected()
    {
        $this->assertSame(Role::Assistant, (new AssistantMessage())->getRole());
    }

    public function testConstructionWithoutToolCallIsPossible()
    {
        $message = new AssistantMessage('foo');

        $this->assertSame('foo', $message->getContent());
        $this->assertNull($message->getToolCalls());
    }

    public function testConstructionWithoutContentIsPossible()
    {
        $toolCall = new ToolCall('foo', 'foo');
        $message = new AssistantMessage(toolCalls: [$toolCall]);

        $this->assertNull($message->getContent());
        $this->assertSame([$toolCall], $message->getToolCalls());
        $this->assertTrue($message->hasToolCalls());
    }

    public function testMessageHasUid()
    {
        $message = new AssistantMessage('foo');

        $this->assertInstanceOf(UuidV7::class, $message->getId());
    }

    public function testDifferentMessagesHaveDifferentUids()
    {
        $message1 = new AssistantMessage('foo');
        $message2 = new AssistantMessage('bar');

        $this->assertNotSame($message1->getId()->toRfc4122(), $message2->getId()->toRfc4122());
        $this->assertIsUuidV7($message1->getId()->toRfc4122());
        $this->assertIsUuidV7($message2->getId()->toRfc4122());
    }

    public function testSameMessagesHaveDifferentUids()
    {
        $message1 = new AssistantMessage('foo');
        $message2 = new AssistantMessage('foo');

        $this->assertNotSame($message1->getId()->toRfc4122(), $message2->getId()->toRfc4122());
        $this->assertIsUuidV7($message1->getId()->toRfc4122());
        $this->assertIsUuidV7($message2->getId()->toRfc4122());
    }

    public function testMessageIdImplementsRequiredInterfaces()
    {
        $message = new AssistantMessage('test');

        $this->assertInstanceOf(AbstractUid::class, $message->getId());
        $this->assertInstanceOf(TimeBasedUidInterface::class, $message->getId());
        $this->assertInstanceOf(UuidV7::class, $message->getId());
    }
}
