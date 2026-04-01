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
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\ThinkingContent;
use Symfony\AI\Platform\Result\ToolCall;
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
        $this->assertTrue($this->normalizer->supportsNormalization(new AssistantMessage(new Text('content'))));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testGetSupportedTypes()
    {
        $this->assertSame([AssistantMessage::class => true], $this->normalizer->getSupportedTypes(null));
    }

    public function testNormalizeWithContent()
    {
        $message = new AssistantMessage(new Text('I am an assistant'));

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
        $message = new AssistantMessage(new Text('Content with tools'), ...$toolCalls);

        $expectedToolCalls = [
            ['id' => 'id1', 'function' => 'function1', 'arguments' => ['param' => 'value']],
            ['id' => 'id2', 'function' => 'function2', 'arguments' => ['param' => 'value2']],
        ];

        $innerNormalizer = $this->createStub(NormalizerInterface::class);
        $innerNormalizer
            ->method('normalize')
            ->willReturnMap([
                [$toolCalls[0], null, [], $expectedToolCalls[0]],
                [$toolCalls[1], null, [], $expectedToolCalls[1]],
            ]);

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
        $message = new AssistantMessage(...$toolCalls);

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
        $message = new AssistantMessage(new Text('The answer is 42.'), new ThinkingContent('Let me think about this...'));

        $expected = [
            'role' => 'assistant',
            'content' => 'The answer is 42.',
            'reasoning_content' => 'Let me think about this...',
        ];

        $this->assertSame($expected, $this->normalizer->normalize($message));
    }

    public function testNormalizeWithoutThinkingContentDoesNotEmitReasoningContent()
    {
        $message = new AssistantMessage(new Text('Just a normal response'));

        $result = $this->normalizer->normalize($message);

        $this->assertArrayNotHasKey('reasoning_content', $result);
        $this->assertSame('Just a normal response', $result['content']);
    }

    public function testNormalizeWithThinkingContentAndToolCalls()
    {
        $message = new AssistantMessage(new Text('Content'), new ToolCall('id1', 'function1', ['param' => 'value']), new ThinkingContent('Reasoning about tool usage'));

        $expectedToolCalls = [['id' => 'id1', 'function' => 'function1', 'arguments' => ['param' => 'value']]];

        $innerNormalizer = $this->createStub(NormalizerInterface::class);
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
