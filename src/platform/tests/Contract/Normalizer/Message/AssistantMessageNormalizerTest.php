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
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Contract\Normalizer\Message\AssistantMessageNormalizer;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\Thinking;
use Symfony\AI\Platform\Message\Content\WebSearch;
use Symfony\AI\Platform\Model;
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
        $toolCall = new ToolCall('id1', 'function1', ['param' => 'value']);
        $message = new AssistantMessage($toolCall);

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
        $message = new AssistantMessage(
            new Thinking('Let me think about this...'),
            new Text('The answer is 42.'),
        );

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

    public function testNormalizeEmitsProviderSpecificPartsWhenNoModelIsBound()
    {
        $webSearch = new WebSearch('symfony ai', 'ws_1', 'completed');
        $message = new AssistantMessage(new Text('Here is what I found'), $webSearch);

        $normalizedPart = ['type' => 'web_search', 'query' => 'symfony ai'];

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->expects($this->once())
            ->method('normalize')
            ->with($webSearch, null, [])
            ->willReturn($normalizedPart);

        $this->normalizer->setNormalizer($innerNormalizer);

        $expected = [
            'role' => 'assistant',
            'content' => 'Here is what I found',
            'content_parts' => [$normalizedPart],
        ];

        $this->assertSame($expected, $this->normalizer->normalize($message));
    }

    public function testNormalizeDropsProviderSpecificPartsWhenAModelIsBound()
    {
        $message = new AssistantMessage(new Text('Here is what I found'), new WebSearch('symfony ai'));

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->expects($this->never())->method('normalize');

        $this->normalizer->setNormalizer($innerNormalizer);

        $expected = [
            'role' => 'assistant',
            'content' => 'Here is what I found',
        ];

        $this->assertSame($expected, $this->normalizer->normalize($message, context: [Contract::CONTEXT_MODEL => new Model('gpt-4o-mini')]));
    }

    public function testNormalizeWithThinkingContentAndToolCalls()
    {
        $toolCall = new ToolCall('id1', 'function1', ['param' => 'value']);
        $message = new AssistantMessage(
            new Text('Content'),
            new Thinking('Reasoning about tool usage'),
            $toolCall,
        );

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
