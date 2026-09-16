<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Contract\Normalizer\Result;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Contract\Normalizer\Result\ToolCallNormalizer;
use Symfony\AI\Platform\Result\ToolCall;

final class ToolCallNormalizerTest extends TestCase
{
    private ToolCallNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new ToolCallNormalizer();
    }

    public function testSupportsNormalization()
    {
        $this->assertTrue($this->normalizer->supportsNormalization(new ToolCall('id', 'function')));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testGetSupportedTypes()
    {
        $this->assertSame([ToolCall::class => true], $this->normalizer->getSupportedTypes(null));
    }

    public function testNormalize()
    {
        $toolCall = new ToolCall('tool_call_123', 'get_weather', ['location' => 'Paris']);

        $this->assertSame([
            'id' => 'tool_call_123',
            'type' => 'function',
            'function' => [
                'name' => 'get_weather',
                'arguments' => '{"location":"Paris"}',
            ],
        ], $this->normalizer->normalize($toolCall));
    }

    public function testNormalizeKeepsForwardSlashesUnescaped()
    {
        $toolCall = new ToolCall('tool_call_123', 'query_assets', ['path' => '/de/shop']);

        $normalized = $this->normalizer->normalize($toolCall);

        $this->assertSame('{"path":"/de/shop"}', $normalized['function']['arguments']);
    }

    public function testNormalizeWithoutArguments()
    {
        $normalized = $this->normalizer->normalize(new ToolCall('tool_call_123', 'now'));

        $this->assertSame('{}', $normalized['function']['arguments']);
    }
}
