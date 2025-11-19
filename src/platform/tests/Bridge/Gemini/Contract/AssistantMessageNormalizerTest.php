<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Gemini\Contract;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Gemini\Contract\AssistantMessageNormalizer;
use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Result\ToolCall;

final class AssistantMessageNormalizerTest extends TestCase
{
    public function testSupportsNormalization()
    {
        $normalizer = new AssistantMessageNormalizer();

        $this->assertTrue($normalizer->supportsNormalization(new AssistantMessage('Hello'), context: [
            Contract::CONTEXT_MODEL => new Gemini('gemini-2.0-flash'),
        ]));
        $this->assertFalse($normalizer->supportsNormalization('not an assistant message'));
    }

    public function testGetSupportedTypes()
    {
        $normalizer = new AssistantMessageNormalizer();

        $this->assertSame([AssistantMessage::class => true], $normalizer->getSupportedTypes(null));
    }

    #[DataProvider('normalizeDataProvider')]
    public function testNormalize(AssistantMessage $message, array $expectedOutput)
    {
        $normalizer = new AssistantMessageNormalizer();

        $normalized = $normalizer->normalize($message);

        $this->assertSame($expectedOutput, $normalized);
    }

    /**
     * @return iterable<string, array{AssistantMessage, array{text?: string, functionCall?: array{id: string, name: string, args?: mixed}}[]}>
     */
    public static function normalizeDataProvider(): iterable
    {
        yield 'assistant message' => [
            new AssistantMessage('Great to meet you. What would you like to know?'),
            [['text' => 'Great to meet you. What would you like to know?']],
        ];
        yield 'function call' => [
            new AssistantMessage(toolCalls: [new ToolCall('id1', 'name1', ['arg1' => '123'])]),
            [['functionCall' => ['id' => 'id1', 'name' => 'name1', 'args' => ['arg1' => '123']]]],
        ];
        yield 'function call without parameters' => [
            new AssistantMessage(toolCalls: [new ToolCall('id1', 'name1')]),
            [['functionCall' => ['id' => 'id1', 'name' => 'name1']]],
        ];
    }
}
