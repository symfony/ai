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
        $this->assertTrue($this->normalizer->supportsNormalization(new AssistantMessage('content')));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testGetSupportedTypes()
    {
        $this->assertSame([AssistantMessage::class => true], $this->normalizer->getSupportedTypes(null));
    }

    public function testNormalizeWithContent()
    {
        $message = new AssistantMessage('I am an assistant');

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->expects($this->once())
            ->method('normalize')
            ->with('I am an assistant', null, [])
            ->willReturn('I am an assistant');

        $this->normalizer->setNormalizer($innerNormalizer);

        $expected = [
            'role' => 'assistant',
            'content' => '"I am an assistant"',
        ];

        $this->assertSame($expected, $this->normalizer->normalize($message));
    }

    public function testNormalizeWithToolCalls()
    {
        $toolCalls = [
            new ToolCall('id1', 'function1', ['param' => 'value']),
            new ToolCall('id2', 'function2', ['param' => 'value2']),
        ];
        $message = new AssistantMessage('Content with tools', $toolCalls);

        $expectedToolCalls = [
            ['id' => 'id1', 'function' => 'function1', 'arguments' => ['param' => 'value']],
            ['id' => 'id2', 'function' => 'function2', 'arguments' => ['param' => 'value2']],
        ];

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->expects($this->exactly(2))
            ->method('normalize')
            ->willReturnCallback(static function ($data, $format, $context) use ($message, $expectedToolCalls) {
                if ('Content with tools' === $data) {
                    return 'Content with tools';
                }
                if ($data === $message->getToolCalls()) {
                    return $expectedToolCalls;
                }
            });

        $this->normalizer->setNormalizer($innerNormalizer);

        $expected = [
            'role' => 'assistant',
            'content' => '"Content with tools"',
            'tool_calls' => $expectedToolCalls,
        ];

        $this->assertSame($expected, $this->normalizer->normalize($message));
    }

    public function testNormalizeWithNullContent()
    {
        $toolCalls = [new ToolCall('id1', 'function1', ['param' => 'value'])];
        $message = new AssistantMessage(null, $toolCalls);

        $expectedToolCalls = [['id' => 'id1', 'function' => 'function1', 'arguments' => ['param' => 'value']]];

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->expects($this->once())
            ->method('normalize')
            ->with($message->getToolCalls(), null, [])
            ->willReturn($expectedToolCalls);

        $this->normalizer->setNormalizer($innerNormalizer);

        $expected = [
            'role' => 'assistant',
            'tool_calls' => $expectedToolCalls,
        ];

        $this->assertSame($expected, $this->normalizer->normalize($message));
    }

    public function testNormalizeWithJsonSerializableContent()
    {
        $content = new class implements \JsonSerializable {
            /**
             * @return array{title: string, ingredients: list<string>}
             */
            public function jsonSerialize(): array
            {
                return ['title' => 'Test Recipe', 'ingredients' => ['flour', 'sugar']];
            }
        };
        $message = new AssistantMessage($content);

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->expects($this->once())
            ->method('normalize')
            ->with($content, null, [])
            ->willReturn(['title' => 'Test Recipe', 'ingredients' => ['flour', 'sugar']]);

        $this->normalizer->setNormalizer($innerNormalizer);

        $expected = [
            'role' => 'assistant',
            'content' => '{"title":"Test Recipe","ingredients":["flour","sugar"]}',
        ];

        $this->assertSame($expected, $this->normalizer->normalize($message));
    }

    public function testNormalizeWithStringableContent()
    {
        $content = new class implements \Stringable {
            public function __toString(): string
            {
                return 'Stringable content here';
            }
        };
        $message = new AssistantMessage($content);

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->expects($this->once())
            ->method('normalize')
            ->with($content, null, [])
            ->willReturn('Stringable content here');

        $this->normalizer->setNormalizer($innerNormalizer);

        $expected = [
            'role' => 'assistant',
            'content' => '"Stringable content here"',
        ];

        $this->assertSame($expected, $this->normalizer->normalize($message));
    }

    public function testNormalizeWithObjectContent()
    {
        $content = new \stdClass();
        $content->property = 'value';
        $message = new AssistantMessage($content);

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->expects($this->once())
            ->method('normalize')
            ->with($content, null, [])
            ->willReturn(['property' => 'value']);

        $this->normalizer->setNormalizer($innerNormalizer);

        $expected = [
            'role' => 'assistant',
            'content' => '{"property":"value"}',
        ];

        $this->assertSame($expected, $this->normalizer->normalize($message));
    }
}
