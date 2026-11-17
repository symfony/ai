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
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tests\Helper\UuidAssertionTrait;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;
use Symfony\Component\Uid\UuidV7;

final class ToolCallMessageTest extends TestCase
{
    use UuidAssertionTrait;

    public function testErrorStatusCanBeChangedWithoutChangingContent()
    {
        $message = new ToolCallMessage(new ToolCall('foo', 'bar'), new Text('Unavailable'));
        $id = $message->getId();
        $content = $message->getContent();
        $this->assertFalse($message->isError());
        $message->setIsError(true);

        $this->assertTrue($message->isError());
        $this->assertSame($id, $message->getId());
        $this->assertSame($content, $message->getContent());
        $message->setIsError(false);
        $this->assertFalse($message->isError());
    }

    public function testConstructionIsPossible()
    {
        $toolCall = new ToolCall('foo', 'bar');
        $obj = new ToolCallMessage($toolCall, new Text('bar'));

        $this->assertSame($toolCall, $obj->getToolCall());
        $this->assertEquals([new Text('bar')], $obj->getContent());
        $this->assertSame('bar', $obj->asText());
    }

    public function testTextOnlyContentFlattensToText()
    {
        $message = new ToolCallMessage(new ToolCall('foo', 'bar'), new Text('first'), new Text('second'));

        $this->assertSame('first second', $message->asText());
    }

    public function testMultimodalContentExposesPartsAndFlattensTextForAsText()
    {
        $image = new Image('binary', 'image/png');
        $parts = [new Text('first'), $image, new Text('second')];

        $message = new ToolCallMessage(new ToolCall('foo', 'bar'), ...$parts);

        $this->assertSame($parts, $message->getContent());
        $this->assertSame('first second', $message->asText());
    }

    public function testContentWithoutTextHasNullAsText()
    {
        $message = new ToolCallMessage(new ToolCall('foo', 'bar'), new Image('binary', 'image/png'));

        $this->assertNull($message->asText());
    }

    public function testMessageHasUid()
    {
        $toolCall = new ToolCall('foo', 'bar');
        $message = new ToolCallMessage($toolCall, new Text('bar'));

        $this->assertInstanceOf(UuidV7::class, $message->getId());
    }

    public function testDifferentMessagesHaveDifferentUids()
    {
        $toolCall = new ToolCall('foo', 'bar');
        $message1 = new ToolCallMessage($toolCall, new Text('bar'));
        $message2 = new ToolCallMessage($toolCall, new Text('baz'));

        $this->assertNotSame($message1->getId()->toRfc4122(), $message2->getId()->toRfc4122());
        $this->assertIsUuidV7($message1->getId()->toRfc4122());
        $this->assertIsUuidV7($message2->getId()->toRfc4122());
    }

    public function testSameMessagesHaveDifferentUids()
    {
        $toolCall = new ToolCall('foo', 'bar');
        $message1 = new ToolCallMessage($toolCall, new Text('bar'));
        $message2 = new ToolCallMessage($toolCall, new Text('bar'));

        $this->assertNotSame($message1->getId()->toRfc4122(), $message2->getId()->toRfc4122());
        $this->assertIsUuidV7($message1->getId()->toRfc4122());
        $this->assertIsUuidV7($message2->getId()->toRfc4122());
    }

    public function testMessageIdImplementsRequiredInterfaces()
    {
        $toolCall = new ToolCall('foo', 'bar');
        $message = new ToolCallMessage($toolCall, new Text('test'));

        $this->assertInstanceOf(AbstractUid::class, $message->getId());
        $this->assertInstanceOf(TimeBasedUidInterface::class, $message->getId());
        $this->assertInstanceOf(UuidV7::class, $message->getId());
    }
}
