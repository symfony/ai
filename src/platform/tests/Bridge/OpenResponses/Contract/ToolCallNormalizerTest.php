<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\OpenResponses\Contract;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenResponses\Contract\ToolCallNormalizer;
use Symfony\AI\Platform\Bridge\OpenResponses\ResponsesModel;
use Symfony\AI\Platform\Contract;
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
        $context = [Contract::CONTEXT_MODEL => new ResponsesModel('gpt-4o')];

        $this->assertTrue($this->normalizer->supportsNormalization(new ToolCall('id', 'function'), null, $context));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass(), null, $context));
    }

    public function testNormalize()
    {
        $toolCall = new ToolCall('call_123', 'get_weather', ['location' => 'Paris']);

        $this->assertSame([
            'arguments' => '{"location":"Paris"}',
            'call_id' => 'call_123',
            'name' => 'get_weather',
            'type' => 'function_call',
        ], $this->normalizer->normalize($toolCall));
    }

    public function testNormalizeKeepsForwardSlashesUnescaped()
    {
        $toolCall = new ToolCall('call_123', 'query_assets', ['path' => '/de/shop']);

        $normalized = $this->normalizer->normalize($toolCall);

        $this->assertSame('{"path":"/de/shop"}', $normalized['arguments']);
    }
}
