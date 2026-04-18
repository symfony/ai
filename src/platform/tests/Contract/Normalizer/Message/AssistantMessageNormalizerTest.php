<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Contract\Normalizer\Message;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Contract\Normalizer\Message\AssistantMessageNormalizer;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ThinkingResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class AssistantMessageNormalizerTest extends TestCase
{
    private AssistantMessageNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new AssistantMessageNormalizer();
    }

    public function testSupportsNormalization()
    {
        $this->assertTrue($this->normalizer->supportsNormalization(new AssistantMessage(new TextResult('content'))));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testGetSupportedTypes()
    {
        $this->assertSame([AssistantMessage::class => true], $this->normalizer->getSupportedTypes(null));
    }

    public function testNormalizeWithContent()
    {
        $message = new AssistantMessage(new TextResult('I am an assistant'));

        $expected = [
            'role' => 'assistant',
            'content' => 'I am an assistant',
        ];

        $this->assertSame($expected, $this->normalizer->normalize($message));
    }

    public function testNormalizeWithToolCalls()
    {
        $toolCalls = [
            new ToolCall('id1', 'function1', ['param' => 'value']),
            new ToolCall('id2', 'function2', ['param' => 'value2']),
        ];
        $message = new AssistantMessage(new MultiPartResult([new TextResult('Content with tools'), new ToolCallResult($toolCalls)]));

        $expectedToolCalls = [
            ['id' => 'id1', 'function' => 'function1', 'arguments' => ['param' => 'value']],
            ['id' => 'id2', 'function' => 'function2', 'arguments' => ['param' => 'value2']],
        ];

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->expects($this->once())
            ->method('normalize')
            ->with($message->getToolCalls(), null, [])
            ->willReturn($expectedToolCalls);

        $this->normalizer->setNormalizer($innerNormalizer);

        $expected = [
            'role' => 'assistant',
            'content' => 'Content with tools',
            'tool_calls' => $expectedToolCalls,
        ];

        $this->assertSame($expected, $this->normalizer->normalize($message));
    }

    public function testNormalizeWithNullContent()
    {
        $toolCalls = [new ToolCall('id1', 'function1', ['param' => 'value'])];
        $message = new AssistantMessage(new ToolCallResult($toolCalls));

        $expectedToolCalls = [['id' => 'id1', 'function' => 'function1', 'arguments' => ['param' => 'value']]];

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->expects($this->once())
            ->method('normalize')
            ->with($message->getToolCalls(), null, [])
            ->willReturn($expectedToolCalls);

        $this->normalizer->setNormalizer($innerNormalizer);

        $result = $this->normalizer->normalize($message);

        $this->assertSame('assistant', $result['role']);
        $this->assertNull($result['content']);
        $this->assertSame($expectedToolCalls, $result['tool_calls']);
    }

    public function testNormalizeWithThinkingContent()
    {
        $message = new AssistantMessage(new MultiPartResult([new ThinkingResult('Let me think about this...'), new TextResult('The answer is 42.')]));

        $expected = [
            'role' => 'assistant',
            'content' => 'The answer is 42.',
            'reasoning_content' => 'Let me think about this...',
        ];

        $this->assertSame($expected, $this->normalizer->normalize($message));
    }

    public function testNormalizeWithoutThinkingContentDoesNotEmitReasoningContent()
    {
        $message = new AssistantMessage(new TextResult('Just a normal response'));

        $result = $this->normalizer->normalize($message);

        $this->assertArrayNotHasKey('reasoning_content', $result);
        $this->assertSame('Just a normal response', $result['content']);
    }

    public function testNormalizeWithThinkingContentAndToolCalls()
    {
        $toolCalls = [new ToolCall('id1', 'function1', ['param' => 'value'])];
        $message = new AssistantMessage(new MultiPartResult([new TextResult('Content'), new ThinkingResult('Reasoning about tool usage'), new ToolCallResult($toolCalls)]));

        $expectedToolCalls = [['id' => 'id1', 'function' => 'function1', 'arguments' => ['param' => 'value']]];

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->expects($this->once())
            ->method('normalize')
            ->with($message->getToolCalls(), null, [])
            ->willReturn($expectedToolCalls);

        $this->normalizer->setNormalizer($innerNormalizer);

        $result = $this->normalizer->normalize($message);

        $this->assertSame('assistant', $result['role']);
        $this->assertSame('Content', $result['content']);
        $this->assertSame($expectedToolCalls, $result['tool_calls']);
        $this->assertSame('Reasoning about tool usage', $result['reasoning_content']);
    }
}
