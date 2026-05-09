<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Anthropic\Contract;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\Anthropic\Contract\AssistantMessageNormalizer;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\CodeExecution;
use Symfony\AI\Platform\Message\Content\ExecutableCode;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\Thinking;
use Symfony\AI\Platform\Result\ToolCall;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class AssistantMessageNormalizerTest extends TestCase
{
    private AssistantMessageNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new AssistantMessageNormalizer();
    }

    public function testSupportsNormalization()
    {
        $model = new Claude(Claude::HAIKU_35);
        $context = [Contract::CONTEXT_MODEL => $model];

        $this->assertTrue($this->normalizer->supportsNormalization(new AssistantMessage(new Text('content')), null, $context));
        $this->assertFalse($this->normalizer->supportsNormalization(new AssistantMessage(new Text('content')), null, []));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass(), null, $context));
    }

    public function testGetSupportedTypes()
    {
        $this->assertSame([AssistantMessage::class => true], $this->normalizer->getSupportedTypes(null));
    }

    public function testNormalizeWithContent()
    {
        $message = new AssistantMessage(new Text('Hello, I am an assistant.'));

        $this->assertSame([
            'role' => 'assistant',
            'content' => 'Hello, I am an assistant.',
        ], $this->normalizer->normalize($message));
    }

    public function testNormalizeWithEmptyMessageProducesEmptyString()
    {
        $message = new AssistantMessage();

        $this->assertSame([
            'role' => 'assistant',
            'content' => '',
        ], $this->normalizer->normalize($message));
    }

    public function testNormalizeWithToolCalls()
    {
        $toolCall = new ToolCall('tool-id', 'some_tool', ['param' => 'value']);
        $message = new AssistantMessage($toolCall);

        $this->assertSame([
            'role' => 'assistant',
            'content' => [
                [
                    'type' => 'tool_use',
                    'id' => 'tool-id',
                    'name' => 'some_tool',
                    'input' => ['param' => 'value'],
                ],
            ],
        ], $this->normalizer->normalize($message));
    }

    public function testNormalizeWithToolCallsAndContent()
    {
        $toolCall = new ToolCall('tool-id', 'some_tool', ['param' => 'value']);
        $message = new AssistantMessage(new Text('Some text before tool use.'), $toolCall);

        $this->assertSame([
            'role' => 'assistant',
            'content' => [
                ['type' => 'text', 'text' => 'Some text before tool use.'],
                [
                    'type' => 'tool_use',
                    'id' => 'tool-id',
                    'name' => 'some_tool',
                    'input' => ['param' => 'value'],
                ],
            ],
        ], $this->normalizer->normalize($message));
    }

    public function testNormalizeWithThinkingContent()
    {
        $message = new AssistantMessage(new Thinking('Let me think about this...', 'sig-abc'));

        $this->assertSame([
            'role' => 'assistant',
            'content' => [
                [
                    'type' => 'thinking',
                    'thinking' => 'Let me think about this...',
                    'signature' => 'sig-abc',
                ],
            ],
        ], $this->normalizer->normalize($message));
    }

    public function testNormalizeWithThinkingContentAndText()
    {
        $message = new AssistantMessage(
            new Thinking('Let me think about this...', 'sig-abc'),
            new Text('The answer is 42.'),
        );

        $this->assertSame([
            'role' => 'assistant',
            'content' => [
                [
                    'type' => 'thinking',
                    'thinking' => 'Let me think about this...',
                    'signature' => 'sig-abc',
                ],
                ['type' => 'text', 'text' => 'The answer is 42.'],
            ],
        ], $this->normalizer->normalize($message));
    }

    public function testNormalizeWithBashCodeExecutionAndResult()
    {
        $message = new AssistantMessage(
            new ExecutableCode('echo hi', 'bash', 'srvtoolu_1'),
            new CodeExecution(true, 'hi', 'srvtoolu_1'),
        );

        $this->assertSame([
            'role' => 'assistant',
            'content' => [
                [
                    'type' => 'server_tool_use',
                    'name' => 'bash_code_execution',
                    'input' => ['command' => 'echo hi'],
                    'id' => 'srvtoolu_1',
                ],
                [
                    'type' => 'bash_code_execution_tool_result',
                    'tool_use_id' => 'srvtoolu_1',
                    'content' => [
                        'type' => 'bash_code_execution_result',
                        'stdout' => 'hi',
                        'stderr' => '',
                        'return_code' => 0,
                        'content' => [],
                    ],
                ],
            ],
        ], $this->normalizer->normalize($message));
    }

    public function testNormalizeWithTextEditorCodeExecutionAndResult()
    {
        $message = new AssistantMessage(
            new ExecutableCode("print('x')", null, 'srvtoolu_2'),
            new CodeExecution(true, null, 'srvtoolu_2'),
        );

        $this->assertSame([
            'role' => 'assistant',
            'content' => [
                [
                    'type' => 'server_tool_use',
                    'name' => 'text_editor_code_execution',
                    'input' => ['file_text' => "print('x')"],
                    'id' => 'srvtoolu_2',
                ],
                [
                    'type' => 'text_editor_code_execution_tool_result',
                    'tool_use_id' => 'srvtoolu_2',
                ],
            ],
        ], $this->normalizer->normalize($message));
    }

    public function testNormalizeWithFailedBashExecution()
    {
        $message = new AssistantMessage(
            new ExecutableCode('false', 'bash', 'srvtoolu_3'),
            new CodeExecution(false, 'oops', 'srvtoolu_3'),
        );

        $this->assertSame([
            'role' => 'assistant',
            'content' => [
                [
                    'type' => 'server_tool_use',
                    'name' => 'bash_code_execution',
                    'input' => ['command' => 'false'],
                    'id' => 'srvtoolu_3',
                ],
                [
                    'type' => 'bash_code_execution_tool_result',
                    'tool_use_id' => 'srvtoolu_3',
                    'content' => [
                        'type' => 'bash_code_execution_result',
                        'stdout' => 'oops',
                        'stderr' => '',
                        'return_code' => 1,
                        'content' => [],
                    ],
                ],
            ],
        ], $this->normalizer->normalize($message));
    }
}
