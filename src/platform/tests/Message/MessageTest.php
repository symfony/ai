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
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\CodeExecution;
use Symfony\AI\Platform\Message\Content\ContentInterface;
use Symfony\AI\Platform\Message\Content\ExecutableCode;
use Symfony\AI\Platform\Message\Content\ImageUrl;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\Thinking;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\CodeExecutionResult;
use Symfony\AI\Platform\Result\ExecutableCodeResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ThinkingResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;

final class MessageTest extends TestCase
{
    public function testCreateSystemMessageWithString()
    {
        $message = Message::forSystem('My amazing system prompt.');

        $this->assertSame('My amazing system prompt.', $message->getContent());
    }

    public function testCreateSystemMessageWithStringable()
    {
        $message = Message::forSystem(new class implements \Stringable {
            public function __toString(): string
            {
                return 'My amazing system prompt.';
            }
        });

        $this->assertSame('My amazing system prompt.', $message->getContent());
    }

    public function testCreateAssistantMessage()
    {
        $message = Message::ofAssistant('It is time to sleep.');

        $this->assertCount(1, $message->getContent());
        $this->assertInstanceOf(Text::class, $message->getContent()[0]);
        $this->assertSame('It is time to sleep.', $message->asText());
    }

    public function testCreateAssistantMessageWithToolCalls()
    {
        $toolCalls = [
            new ToolCall('call_123456', 'my_tool', ['foo' => 'bar']),
            new ToolCall('call_456789', 'my_faster_tool'),
        ];
        $message = Message::ofAssistant(...$toolCalls);

        $this->assertCount(2, $message->getToolCalls());
        $this->assertTrue($message->hasToolCalls());
    }

    public function testCreateUserMessageWithString()
    {
        $message = Message::ofUser('Hi, my name is John.');

        $this->assertCount(1, $message->getContent());
        $this->assertInstanceOf(Text::class, $message->getContent()[0]);
        $this->assertSame('Hi, my name is John.', $message->getContent()[0]->getText());
    }

    public function testCreateUserMessageWithStringable()
    {
        $message = Message::ofUser(new class implements \Stringable {
            public function __toString(): string
            {
                return 'Hi, my name is John.';
            }
        });

        $this->assertCount(1, $message->getContent());
        $this->assertInstanceOf(Text::class, $message->getContent()[0]);
        $this->assertSame('Hi, my name is John.', $message->getContent()[0]->getText());
    }

    public function testCreateUserMessageContentInterfaceImplementingStringable()
    {
        $message = Message::ofUser(new class implements ContentInterface, \Stringable {
            public function __toString(): string
            {
                return 'I am a ContentInterface!';
            }
        });

        $this->assertCount(1, $message->getContent());
        $this->assertInstanceOf(ContentInterface::class, $message->getContent()[0]);
    }

    public function testCreateUserMessageWithTextContent()
    {
        $text = new Text('Hi, my name is John.');
        $message = Message::ofUser($text);

        $this->assertSame([$text], $message->getContent());
    }

    public function testCreateUserMessageWithImages()
    {
        $message = Message::ofUser(
            new Text('Hi, my name is John.'),
            new ImageUrl('http://images.local/my-image.png'),
            'The following image is a joke.',
            new ImageUrl('http://images.local/my-image2.png'),
        );

        $this->assertCount(4, $message->getContent());
    }

    public function testCreateAssistantMessageFromMultiPartResultMapsKnownResultTypes()
    {
        $result = new MultiPartResult([
            new ThinkingResult('Reasoning...', 'sig'),
            new TextResult('Visible answer.'),
            new ToolCallResult([new ToolCall('id1', 'fn', ['x' => 1])]),
            new ExecutableCodeResult('echo hi', 'bash', 'srvtoolu_1'),
            new CodeExecutionResult(true, 'hi', 'srvtoolu_1'),
        ]);

        $message = Message::ofAssistant($result);

        $parts = $message->getContent();
        $this->assertCount(5, $parts);
        $this->assertInstanceOf(Thinking::class, $parts[0]);
        $this->assertInstanceOf(Text::class, $parts[1]);
        $this->assertInstanceOf(ToolCall::class, $parts[2]);
        $this->assertInstanceOf(ExecutableCode::class, $parts[3]);
        $this->assertSame('echo hi', $parts[3]->getCode());
        $this->assertSame('bash', $parts[3]->getLanguage());
        $this->assertSame('srvtoolu_1', $parts[3]->getId());
        $this->assertInstanceOf(CodeExecution::class, $parts[4]);
        $this->assertTrue($parts[4]->isSucceeded());
        $this->assertSame('hi', $parts[4]->getOutput());
        $this->assertSame('srvtoolu_1', $parts[4]->getId());
    }

    public function testCreateAssistantMessageFromUnsupportedResultThrows()
    {
        $result = BinaryResult::fromBase64(base64_encode('binary'), 'image/png');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Unsupported assistant message part of type "%s".', $result::class));

        Message::ofAssistant($result);
    }

    public function testCreateAssistantMessageFromMultiPartResultThrowsOnUnsupportedInnerPart()
    {
        $result = new MultiPartResult([
            new TextResult('Visible answer.'),
            BinaryResult::fromBase64(base64_encode('binary'), 'image/png'),
        ]);

        $this->expectException(InvalidArgumentException::class);

        Message::ofAssistant($result);
    }

    public function testCreateToolCallMessage()
    {
        $toolCall = new ToolCall('call_123456', 'my_tool', ['foo' => 'bar']);
        $message = Message::ofToolCall($toolCall, 'Foo bar.');

        $this->assertSame('Foo bar.', $message->getContent());
        $this->assertSame($toolCall, $message->getToolCall());
    }
}
