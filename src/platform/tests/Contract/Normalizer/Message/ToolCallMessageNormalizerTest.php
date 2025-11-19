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
use Symfony\AI\Platform\Contract\Normalizer\Message\ToolCallMessageNormalizer;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class ToolCallMessageNormalizerTest extends TestCase
{
    private ToolCallMessageNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new ToolCallMessageNormalizer();
    }

    public function testSupportsNormalization()
    {
        $toolCallMessage = new ToolCallMessage(new ToolCall('id', 'function'), 'content');

        $this->assertTrue($this->normalizer->supportsNormalization($toolCallMessage));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testGetSupportedTypes()
    {
        $this->assertSame([ToolCallMessage::class => true], $this->normalizer->getSupportedTypes(null));
    }

    public function testNormalize()
    {
        $toolCall = new ToolCall('tool_call_123', 'get_weather', ['location' => 'Paris']);
        $message = new ToolCallMessage($toolCall, 'Weather data for Paris');
        $expectedContent = 'Normalized weather data for Paris';

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->expects($this->once())
            ->method('normalize')
            ->with($message->getContent(), null, [])
            ->willReturn($expectedContent);

        $this->normalizer->setNormalizer($innerNormalizer);

        $expected = [
            'role' => 'tool',
            'content' => $expectedContent,
            'tool_call_id' => 'tool_call_123',
        ];

        $this->assertSame($expected, $this->normalizer->normalize($message));
    }
}
