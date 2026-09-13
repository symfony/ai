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
use Symfony\AI\Platform\Message\Content\ComputerCall;
use Symfony\AI\Platform\Message\Content\ContentInterface;
use Symfony\AI\Platform\Message\Content\CustomToolCall;
use Symfony\AI\Platform\Message\Content\ExecutableCode;
use Symfony\AI\Platform\Message\Content\File;
use Symfony\AI\Platform\Message\Content\FileSearch;
use Symfony\AI\Platform\Message\Content\ImageUrl;
use Symfony\AI\Platform\Message\Content\LocalShellCall;
use Symfony\AI\Platform\Message\Content\McpApprovalRequest;
use Symfony\AI\Platform\Message\Content\McpCall;
use Symfony\AI\Platform\Message\Content\McpListTools;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\Thinking;
use Symfony\AI\Platform\Message\Content\WebSearch;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\CodeExecutionResult;
use Symfony\AI\Platform\Result\ComputerCallResult;
use Symfony\AI\Platform\Result\CustomToolCallResult;
use Symfony\AI\Platform\Result\ExecutableCodeResult;
use Symfony\AI\Platform\Result\FileSearchResult;
use Symfony\AI\Platform\Result\LocalShellCallResult;
use Symfony\AI\Platform\Result\McpApprovalRequestResult;
use Symfony\AI\Platform\Result\McpCallResult;
use Symfony\AI\Platform\Result\McpListToolsResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ThinkingResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Result\WebSearchResult;

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

    public function testCreateAssistantMessageMapsServerToolResultTypes()
    {
        $result = new MultiPartResult([
            new WebSearchResult('latest AI news', 'ws_1', 'completed', ['latest AI news', 'OpenAI recent announcements'], 'provider-signature'),
            new FileSearchResult(['q'], [['file_id' => 'file-1']], 'fs_1', 'completed'),
            new McpCallResult('deepwiki', 'ask', '{"q":1}', 'out', null, 'mcp_1', 'completed'),
            new McpListToolsResult('deepwiki', [['name' => 'ask']], 'mcpl_1'),
            new McpApprovalRequestResult('deepwiki', 'ask', '{"q":1}', 'mcpr_1'),
            new ComputerCallResult(['type' => 'click'], 'call_1', [], 'cu_1', 'completed'),
            new LocalShellCallResult(['bash', '-lc', 'ls'], 'call_2', 'lsh_1', 'completed'),
            new CustomToolCallResult('x_search', 'latest AI news', 'ctc_1', 'completed'),
            new TextResult('Visible answer.'),
        ]);

        $message = Message::ofAssistant($result);

        $parts = $message->getContent();
        $this->assertCount(9, $parts);

        $this->assertInstanceOf(WebSearch::class, $parts[0]);
        $this->assertSame('latest AI news', $parts[0]->getQuery());
        $this->assertSame(['latest AI news', 'OpenAI recent announcements'], $parts[0]->getQueries());
        $this->assertSame('provider-signature', $parts[0]->getSignature());

        $this->assertInstanceOf(FileSearch::class, $parts[1]);
        $this->assertSame(['q'], $parts[1]->getQueries());
        $this->assertSame([['file_id' => 'file-1']], $parts[1]->getResults());

        $this->assertInstanceOf(McpCall::class, $parts[2]);
        $this->assertSame('deepwiki', $parts[2]->getServerLabel());
        $this->assertSame('out', $parts[2]->getOutput());

        $this->assertInstanceOf(McpListTools::class, $parts[3]);
        $this->assertSame([['name' => 'ask']], $parts[3]->getTools());

        $this->assertInstanceOf(McpApprovalRequest::class, $parts[4]);
        $this->assertSame('ask', $parts[4]->getName());

        $this->assertInstanceOf(ComputerCall::class, $parts[5]);
        $this->assertSame(['type' => 'click'], $parts[5]->getAction());
        $this->assertSame('call_1', $parts[5]->getCallId());

        $this->assertInstanceOf(LocalShellCall::class, $parts[6]);
        $this->assertSame(['bash', '-lc', 'ls'], $parts[6]->getCommand());

        $this->assertInstanceOf(CustomToolCall::class, $parts[7]);
        $this->assertSame('x_search', $parts[7]->getName());
        $this->assertSame('latest AI news', $parts[7]->getInput());
        $this->assertSame('ctc_1', $parts[7]->getId());
        $this->assertSame('completed', $parts[7]->getStatus());

        $this->assertInstanceOf(Text::class, $parts[8]);
    }

    public function testCreateAssistantMessageFromBinaryResultKeepsItAsAFile()
    {
        $result = new MultiPartResult([
            BinaryResult::fromBase64(base64_encode('binary'), 'image/png'),
            new ToolCallResult([new ToolCall('id1', 'tool1')]),
        ]);

        $content = Message::ofAssistant($result)->getContent();

        $this->assertInstanceOf(File::class, $content[0]);
        $this->assertSame('image/png', $content[0]->getFormat());
        $this->assertInstanceOf(ToolCall::class, $content[1]);
    }

    public function testCreateAssistantMessageFromUnsupportedResultThrows()
    {
        $result = new ObjectResult(new \stdClass());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Unsupported assistant message part of type "%s".', $result::class));

        Message::ofAssistant($result);
    }

    public function testCreateAssistantMessageFromMultiPartResultThrowsOnUnsupportedInnerPart()
    {
        $result = new MultiPartResult([
            new TextResult('Visible answer.'),
            new ObjectResult(new \stdClass()),
        ]);

        $this->expectException(InvalidArgumentException::class);

        Message::ofAssistant($result);
    }

    public function testCreateToolCallMessage()
    {
        $toolCall = new ToolCall('call_123456', 'my_tool', ['foo' => 'bar']);
        $message = Message::ofToolCall($toolCall, 'Foo bar.');

        $this->assertSame('Foo bar.', $message->asText());
        $this->assertSame($toolCall, $message->getToolCall());
        $this->assertCount(1, $message->getContent());
        $this->assertInstanceOf(Text::class, $message->getContent()[0]);
    }

    public function testCreateToolCallMessageWithStringable()
    {
        $toolCall = new ToolCall('call_123456', 'my_tool', ['foo' => 'bar']);
        $message = Message::ofToolCall($toolCall, new class implements \Stringable {
            public function __toString(): string
            {
                return 'Foo bar.';
            }
        });

        $this->assertCount(1, $message->getContent());
        $this->assertInstanceOf(Text::class, $message->getContent()[0]);
        $this->assertSame('Foo bar.', $message->asText());
    }

    public function testCreateToolCallMessageWithContentInterface()
    {
        $toolCall = new ToolCall('call_123456', 'my_tool', ['foo' => 'bar']);
        $text = new Text('Foo bar.');
        $message = Message::ofToolCall($toolCall, $text);

        $this->assertSame([$text], $message->getContent());
    }

    public function testCreateToolCallMessageWithMultimodalContent()
    {
        $toolCall = new ToolCall('call_123456', 'screenshot', []);
        $message = Message::ofToolCall(
            $toolCall,
            'Here is the screenshot.',
            new ImageUrl('http://images.local/my-image.png'),
        );

        $this->assertCount(2, $message->getContent());
        $this->assertInstanceOf(Text::class, $message->getContent()[0]);
        $this->assertInstanceOf(ImageUrl::class, $message->getContent()[1]);
        $this->assertSame('Here is the screenshot.', $message->asText());
    }
}
