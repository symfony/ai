<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\Anthropic\Contract\ToolCallMessageNormalizer;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;
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

    public function testSupportsClaudeOnly()
    {
        $message = new ToolCallMessage(new ToolCall('id', 'name'), new Text('result'));

        $this->assertTrue($this->normalizer->supportsNormalization($message, null, [Contract::CONTEXT_MODEL => new Claude('claude')]));
        $this->assertFalse($this->normalizer->supportsNormalization($message, null, [Contract::CONTEXT_MODEL => new Gpt('gpt-4o')]));
    }

    public function testNormalizeStringContent()
    {
        $message = new ToolCallMessage(new ToolCall('tool_123', 'get_weather'), new Text('Sunny in Paris'));

        $this->assertSame([
            'role' => 'user',
            'content' => [
                [
                    'type' => 'tool_result',
                    'tool_use_id' => 'tool_123',
                    'content' => 'Sunny in Paris',
                ],
            ],
        ], $this->normalizer->normalize($message));
    }

    public function testNormalizeMultimodalContentEmitsBlocks()
    {
        $text = new Text('Here is the screenshot');
        $image = new Image('binary', 'image/png');
        $message = new ToolCallMessage(new ToolCall('tool_123', 'screenshot'), $text, $image);

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->expects($this->exactly(2))
            ->method('normalize')
            ->willReturnMap([
                [$text, null, [], ['type' => 'text', 'text' => 'Here is the screenshot']],
                [$image, null, [], ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/png', 'data' => 'YmluYXJ5']]],
            ]);

        $this->normalizer->setNormalizer($innerNormalizer);

        $this->assertSame([
            'role' => 'user',
            'content' => [
                [
                    'type' => 'tool_result',
                    'tool_use_id' => 'tool_123',
                    'content' => [
                        ['type' => 'text', 'text' => 'Here is the screenshot'],
                        ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/png', 'data' => 'YmluYXJ5']],
                    ],
                ],
            ],
        ], $this->normalizer->normalize($message));
    }
}
