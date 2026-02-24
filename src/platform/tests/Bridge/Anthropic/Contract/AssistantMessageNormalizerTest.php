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

        $this->assertTrue($this->normalizer->supportsNormalization(new AssistantMessage('content'), null, $context));
        $this->assertFalse($this->normalizer->supportsNormalization(new AssistantMessage('content'), null, []));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass(), null, $context));
    }

    public function testGetSupportedTypes()
    {
        $this->assertSame([AssistantMessage::class => true], $this->normalizer->getSupportedTypes(null));
    }

    public function testNormalizeWithContent()
    {
        $message = new AssistantMessage('Hello, I am an assistant.');

        $this->assertSame([
            'role' => 'assistant',
            'content' => 'Hello, I am an assistant.',
        ], $this->normalizer->normalize($message));
    }

    public function testNormalizeWithNullContentProducesEmptyString()
    {
        $message = new AssistantMessage(null);

        $this->assertSame([
            'role' => 'assistant',
            'content' => '',
        ], $this->normalizer->normalize($message));
    }

    public function testNormalizeWithToolCalls()
    {
        $toolCall = new ToolCall('tool-id', 'some_tool', ['param' => 'value']);
        $message = new AssistantMessage(null, [$toolCall]);

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
        $message = new AssistantMessage('Some text before tool use.', [$toolCall]);

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
        $message = new AssistantMessage(null, null, 'Let me think about this...', 'sig-abc');

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
        $message = new AssistantMessage('The answer is 42.', null, 'Let me think about this...', 'sig-abc');

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
}
