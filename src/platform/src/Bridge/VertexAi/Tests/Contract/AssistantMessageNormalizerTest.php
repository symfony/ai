<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\VertexAi\Tests\Contract;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\VertexAi\Contract\AssistantMessageNormalizer;
use Symfony\AI\Platform\Bridge\VertexAi\Gemini\Model;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\Thinking;
use Symfony\AI\Platform\Result\ToolCall;

final class AssistantMessageNormalizerTest extends TestCase
{
    public function testSupportsNormalization()
    {
        $normalizer = new AssistantMessageNormalizer();

        $this->assertTrue($normalizer->supportsNormalization(new AssistantMessage(new Text('Hello')), context: [
            Contract::CONTEXT_MODEL => new Model('gemini-2.5-pro'),
        ]));
        $this->assertFalse($normalizer->supportsNormalization('not an assistant message'));
    }

    public function testGetSupportedTypes()
    {
        $normalizer = new AssistantMessageNormalizer();

        $this->assertSame([AssistantMessage::class => true], $normalizer->getSupportedTypes(null));
    }

    /**
     * @param array{array{text: string, functionCall?: array{name: string, args?: array<int|string, mixed>}}} $expectedOutput
     */
    #[DataProvider('normalizeDataProvider')]
    public function testNormalize(AssistantMessage $message, array $expectedOutput)
    {
        $normalizer = new AssistantMessageNormalizer();

        $normalized = $normalizer->normalize($message);

        $this->assertSame($expectedOutput, $normalized);
    }

    /**
     * @return iterable<string, array{
     *     AssistantMessage,
     *     array{text?: string, functionCall?: array{name: string, args?: mixed}}[]
     * }>
     */
    public static function normalizeDataProvider(): iterable
    {
        yield 'assistant message' => [
            new AssistantMessage(new Text('Great to meet you. What would you like to know?')),
            [['text' => 'Great to meet you. What would you like to know?']],
        ];
        yield 'function call' => [
            new AssistantMessage(new ToolCall('name1', 'name1', ['arg1' => '123'])),
            [['functionCall' => ['name' => 'name1', 'args' => ['arg1' => '123']]]],
        ];
        yield 'function call without parameters' => [
            new AssistantMessage(new ToolCall('name1', 'name1')),
            [['functionCall' => ['name' => 'name1']]],
        ];
        yield 'thinking with signature' => [
            new AssistantMessage(
                new Thinking('Reasoning step.', 'sig_v1'),
                new Text('Answer.'),
            ),
            [
                ['text' => 'Reasoning step.', 'thought' => true, 'thoughtSignature' => 'sig_v1'],
                ['text' => 'Answer.'],
            ],
        ];
        yield 'thinking without signature' => [
            new AssistantMessage(new Thinking('Quick thought.')),
            [['text' => 'Quick thought.', 'thought' => true]],
        ];
        yield 'multiple thinking parts with signatures' => [
            new AssistantMessage(
                new Thinking('First thought.', 'sig_1'),
                new Text('Intermediate.'),
                new Thinking('Second thought.', 'sig_2'),
            ),
            [
                ['text' => 'First thought.', 'thought' => true, 'thoughtSignature' => 'sig_1'],
                ['text' => 'Intermediate.'],
                ['text' => 'Second thought.', 'thought' => true, 'thoughtSignature' => 'sig_2'],
            ],
        ];
        yield 'signed text part (non-thought)' => [
            new AssistantMessage(new Text('Signed visible text.', 'sig_text')),
            [
                ['text' => 'Signed visible text.', 'thoughtSignature' => 'sig_text'],
            ],
        ];
        yield 'signed function call' => [
            new AssistantMessage(new ToolCall('id1', 'run', ['x' => 1], 'sig_call')),
            [
                ['functionCall' => ['name' => 'run', 'args' => ['x' => 1]], 'thoughtSignature' => 'sig_call'],
            ],
        ];
    }
}
