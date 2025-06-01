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
use Symfony\AI\Platform\Response\ToolCall;

#[CoversClass(AssistantMessage::class)]
#[UsesClass(ToolCall::class)]
#[Small]
final class AssistantMessageTest extends TestCase
{
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
}
