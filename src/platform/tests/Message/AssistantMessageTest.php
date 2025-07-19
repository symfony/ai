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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Role;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tests\Helper\UuidAssertionTrait;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;
use Symfony\Component\Uid\UuidV7;

#[CoversClass(AssistantMessage::class)]
#[UsesClass(ToolCall::class)]
#[Small]
final class AssistantMessageTest extends TestCase
{
    use UuidAssertionTrait;

    #[Test]
    public function theRoleOfTheMessageIsAsExpected(): void
    {
        self::assertSame(Role::Assistant, (new AssistantMessage())->getRole());
    }

    #[Test]
    public function constructionWithoutToolCallIsPossible(): void
    {
        $message = new AssistantMessage('foo');

        self::assertSame('foo', $message->content);
        self::assertNull($message->toolCalls);
    }

    #[Test]
    public function constructionWithoutContentIsPossible(): void
    {
        $toolCall = new ToolCall('foo', 'foo');
        $message = new AssistantMessage(toolCalls: [$toolCall]);

        self::assertNull($message->content);
        self::assertSame([$toolCall], $message->toolCalls);
        self::assertTrue($message->hasToolCalls());
    }

    #[Test]
    public function messageHasUid(): void
    {
        $message = new AssistantMessage('foo');

        self::assertInstanceOf(UuidV7::class, $message->id);
        self::assertInstanceOf(UuidV7::class, $message->getId());
        self::assertSame($message->id, $message->getId());
    }

    #[Test]
    public function differentMessagesHaveDifferentUids(): void
    {
        $message1 = new AssistantMessage('foo');
        $message2 = new AssistantMessage('bar');

        self::assertNotSame($message1->getId()->toRfc4122(), $message2->getId()->toRfc4122());
        self::assertIsUuidV7($message1->getId()->toRfc4122());
        self::assertIsUuidV7($message2->getId()->toRfc4122());
    }

    #[Test]
    public function sameMessagesHaveDifferentUids(): void
    {
        $message1 = new AssistantMessage('foo');
        $message2 = new AssistantMessage('foo');

        self::assertNotSame($message1->getId()->toRfc4122(), $message2->getId()->toRfc4122());
        self::assertIsUuidV7($message1->getId()->toRfc4122());
        self::assertIsUuidV7($message2->getId()->toRfc4122());
    }

    #[Test]
    public function messageIdImplementsRequiredInterfaces(): void
    {
        $message = new AssistantMessage('test');

        self::assertInstanceOf(AbstractUid::class, $message->getId());
        self::assertInstanceOf(TimeBasedUidInterface::class, $message->getId());
        self::assertInstanceOf(UuidV7::class, $message->getId());
    }
}
