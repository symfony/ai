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
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\Thinking;
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
        $message = new AssistantMessage(new Text('foo'));

        $this->assertSame('foo', $message->asText());
        $this->assertSame([], $message->getToolCalls());
        $this->assertFalse($message->hasToolCalls());
    }

    public function testConstructionWithoutContentIsPossible()
    {
        $toolCall = new ToolCall('foo', 'foo');
        $message = new AssistantMessage($toolCall);

        $this->assertNull($message->asText());
        $this->assertSame([$toolCall], $message->getToolCalls());
        $this->assertTrue($message->hasToolCalls());
    }

    public function testConstructionWithThinkingIsPossible()
    {
        $thinking = new Thinking('reasoning', 'sig');
        $message = new AssistantMessage($thinking, new Text('answer'));

        $this->assertTrue($message->hasThinking());
        $this->assertSame([$thinking], $message->getThinking());
        $this->assertSame('answer', $message->asText());
    }

    public function testGetContentReturnsAllParts()
    {
        $thinking = new Thinking('reasoning');
        $text = new Text('answer');
        $toolCall = new ToolCall('id', 'name');
        $message = new AssistantMessage($thinking, $text, $toolCall);

        $this->assertSame([$thinking, $text, $toolCall], $message->getContent());
    }

    public function testAsTextConcatenatesMultipleTextParts()
    {
        $message = new AssistantMessage(new Text('Hello, '), new Text('world!'));

        $this->assertSame('Hello, world!', $message->asText());
    }

    public function testMessageHasUid()
    {
        $message = new AssistantMessage(new Text('foo'));

        $this->assertInstanceOf(UuidV7::class, $message->getId());
    }

    public function testDifferentMessagesHaveDifferentUids()
    {
        $message1 = new AssistantMessage(new Text('foo'));
        $message2 = new AssistantMessage(new Text('bar'));

        $this->assertNotSame($message1->getId()->toRfc4122(), $message2->getId()->toRfc4122());
        $this->assertIsUuidV7($message1->getId()->toRfc4122());
        $this->assertIsUuidV7($message2->getId()->toRfc4122());
    }

    public function testSameMessagesHaveDifferentUids()
    {
        $message1 = new AssistantMessage(new Text('foo'));
        $message2 = new AssistantMessage(new Text('foo'));

        $this->assertNotSame($message1->getId()->toRfc4122(), $message2->getId()->toRfc4122());
        $this->assertIsUuidV7($message1->getId()->toRfc4122());
        $this->assertIsUuidV7($message2->getId()->toRfc4122());
    }

    public function testMessageIdImplementsRequiredInterfaces()
    {
        $message = new AssistantMessage(new Text('test'));

        $this->assertInstanceOf(AbstractUid::class, $message->getId());
        $this->assertInstanceOf(TimeBasedUidInterface::class, $message->getId());
        $this->assertInstanceOf(UuidV7::class, $message->getId());
    }
}
