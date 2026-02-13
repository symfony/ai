<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Tests\Nova\Contract;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\Contract\AssistantMessageNormalizer;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\Nova;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class AssistantMessageNormalizerTest extends TestCase
{
    public function testSupportsNormalization()
    {
        $normalizer = new AssistantMessageNormalizer();

        $this->assertTrue($normalizer->supportsNormalization(new AssistantMessage('Hello'), context: [
            Contract::CONTEXT_MODEL => new Nova('nova-pro'),
        ]));
        $this->assertFalse($normalizer->supportsNormalization('not an assistant message'));
    }

    public function testGetSupportedTypes()
    {
        $normalizer = new AssistantMessageNormalizer();

        $this->assertSame([AssistantMessage::class => true], $normalizer->getSupportedTypes(null));
    }

    /**
     * @param array{role: 'assistant', content: array<array{toolUse?: array{toolUseId: string, name: string, input: mixed}, text?: string}>} $expectedOutput
     */
    #[DataProvider('normalizeDataProvider')]
    public function testNormalize(AssistantMessage $message, array $expectedOutput)
    {
        $normalizer = new AssistantMessageNormalizer();

        $normalized = $normalizer->normalize($message);

        $this->assertEquals($expectedOutput, $normalized);
    }

    /**
     * @return iterable<string, array{
     *     0: AssistantMessage,
     *     1: array{
     *         role: 'assistant',
     *         content: array<array{
     *             toolUse?: array{toolUseId: string, name: string, input: mixed},
     *             text?: string
     *         }>
     *     }
     * }>
     */
    public static function normalizeDataProvider(): iterable
    {
        yield 'assistant message' => [
            new AssistantMessage('Great to meet you. What would you like to know?'),
            [
                'role' => 'assistant',
                'content' => [['text' => 'Great to meet you. What would you like to know?']],
            ],
        ];
        yield 'function call' => [
            new AssistantMessage(toolCalls: [new ToolCall('id1', 'name1', ['arg1' => '123'])]),
            [
                'role' => 'assistant',
                'content' => [
                    [
                        'toolUse' => [
                            'toolUseId' => 'id1',
                            'name' => 'name1',
                            'input' => ['arg1' => '123'],
                        ],
                    ],
                ],
            ],
        ];
        yield 'function call without parameters' => [
            new AssistantMessage(toolCalls: [new ToolCall('id1', 'name1')]),
            [
                'role' => 'assistant',
                'content' => [
                    [
                        'toolUse' => [
                            'toolUseId' => 'id1',
                            'name' => 'name1',
                            'input' => new \stdClass(),
                        ],
                    ],
                ],
            ],
        ];
    }

    public function testNormalizeWithStringableContent()
    {
        $content = new class implements \Stringable {
            public function __toString(): string
            {
                return 'Stringable content';
            }
        };
        $message = new AssistantMessage($content);
        $normalizer = new AssistantMessageNormalizer();

        $normalized = $normalizer->normalize($message);

        $this->assertSame([
            'role' => 'assistant',
            'content' => [['text' => 'Stringable content']],
        ], $normalized);
    }

    public function testNormalizeWithJsonSerializableContent()
    {
        $content = new class implements \JsonSerializable {
            public function jsonSerialize(): array
            {
                return ['title' => 'Test', 'value' => 123];
            }
        };
        $message = new AssistantMessage($content);
        $normalizer = new AssistantMessageNormalizer();

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->expects($this->once())
            ->method('normalize')
            ->with($content, null, [])
            ->willReturn(['title' => 'Test', 'value' => 123]);
        $normalizer->setNormalizer($innerNormalizer);

        $normalized = $normalizer->normalize($message);

        $this->assertSame('assistant', $normalized['role']);
        $this->assertCount(1, $normalized['content']);
        $this->assertIsString($normalized['content'][0]['text']);
        $this->assertStringContainsString('"title":"Test"', $normalized['content'][0]['text']);
        $this->assertStringContainsString('"value":123', $normalized['content'][0]['text']);
    }

    public function testNormalizeWithObjectContent()
    {
        $content = new \stdClass();
        $content->property = 'value';
        $message = new AssistantMessage($content);
        $normalizer = new AssistantMessageNormalizer();

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->expects($this->once())
            ->method('normalize')
            ->with($content, null, [])
            ->willReturn(['property' => 'value']);
        $normalizer->setNormalizer($innerNormalizer);

        $normalized = $normalizer->normalize($message);

        $this->assertSame('assistant', $normalized['role']);
        $this->assertCount(1, $normalized['content']);
        $this->assertIsString($normalized['content'][0]['text']);
        $this->assertStringContainsString('"property":"value"', $normalized['content'][0]['text']);
    }
}
