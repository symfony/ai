<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\VertexAi\Contract;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\VertexAi\Contract\ToolCallMessageNormalizer;
use Symfony\AI\Platform\Bridge\VertexAi\Gemini\Model;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Result\ToolCall;

final class ToolCallMessageNormalizerTest extends TestCase
{
    private ToolCallMessageNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new ToolCallMessageNormalizer();
    }

    public function testSupportsModel()
    {
        $this->assertTrue($this->normalizer->supportsNormalization(
            new ToolCallMessage(new ToolCall('id', 'fn'), '{}'),
            context: ['model' => new Model('gemini-2.5-flash')],
        ));
    }

    public function testGetSupportedTypes()
    {
        $this->assertSame([ToolCallMessage::class => true], $this->normalizer->getSupportedTypes(null));
    }

    public function testPassesAssociativeArrayResponseThrough()
    {
        $message = new ToolCallMessage(
            new ToolCall('call_1', 'get_weather'),
            json_encode(['city' => 'Vienna', 'tempC' => 21]),
        );

        $normalized = $this->normalizer->normalize($message);

        $this->assertSame([[
            'functionResponse' => [
                'name' => 'get_weather',
                'response' => ['city' => 'Vienna', 'tempC' => 21],
            ],
        ]], $normalized);
    }

    public function testWrapsListResponseUnderItemsKey()
    {
        $message = new ToolCallMessage(
            new ToolCall('call_1', 'list_promos'),
            json_encode([
                ['name' => 'Early bird', 'discount' => 0.1],
                ['name' => 'Late deal', 'discount' => 0.2],
            ]),
        );

        $normalized = $this->normalizer->normalize($message);

        $this->assertSame([
            'items' => [
                ['name' => 'Early bird', 'discount' => 0.1],
                ['name' => 'Late deal', 'discount' => 0.2],
            ],
        ], $normalized[0]['functionResponse']['response']);
    }

    public function testWrapsEmptyListUnderItemsKey()
    {
        $message = new ToolCallMessage(
            new ToolCall('call_1', 'list_promos'),
            json_encode([]),
        );

        $normalized = $this->normalizer->normalize($message);

        $this->assertSame(['items' => []], $normalized[0]['functionResponse']['response']);
    }

    public function testWrapsScalarResponseUnderRawResponseKey()
    {
        $message = new ToolCallMessage(
            new ToolCall('call_1', 'cancel_booking'),
            json_encode('Booking BZGBDJ has been cancelled.'),
        );

        $normalized = $this->normalizer->normalize($message);

        $this->assertSame(
            ['rawResponse' => 'Booking BZGBDJ has been cancelled.'],
            $normalized[0]['functionResponse']['response'],
        );
    }

    public function testWrapsNonJsonContentAsRawResponse()
    {
        $message = new ToolCallMessage(
            new ToolCall('call_1', 'echo_status'),
            'plain text reply, not JSON',
        );

        $normalized = $this->normalizer->normalize($message);

        $this->assertSame(
            ['rawResponse' => 'plain text reply, not JSON'],
            $normalized[0]['functionResponse']['response'],
        );
    }

    public function testWrapsNullResponseUnderRawResponseKey()
    {
        $message = new ToolCallMessage(
            new ToolCall('call_1', 'maybe_get_user'),
            json_encode(null),
        );

        $normalized = $this->normalizer->normalize($message);

        $this->assertSame(['rawResponse' => null], $normalized[0]['functionResponse']['response']);
    }

    public function testWrapsBooleanFalseAsRawResponse()
    {
        $message = new ToolCallMessage(
            new ToolCall('call_1', 'is_active'),
            json_encode(false),
        );

        $normalized = $this->normalizer->normalize($message);

        $this->assertSame(['rawResponse' => false], $normalized[0]['functionResponse']['response']);
    }

    public function testWrapsIntegerZeroAsRawResponse()
    {
        $message = new ToolCallMessage(
            new ToolCall('call_1', 'count_items'),
            json_encode(0),
        );

        $normalized = $this->normalizer->normalize($message);

        $this->assertSame(['rawResponse' => 0], $normalized[0]['functionResponse']['response']);
    }

    public function testIncludesToolCallNameInFunctionResponse()
    {
        $message = new ToolCallMessage(
            new ToolCall('call_42', 'my_specific_tool'),
            json_encode(['ok' => true]),
        );

        $normalized = $this->normalizer->normalize($message);

        $this->assertSame('my_specific_tool', $normalized[0]['functionResponse']['name']);
    }
}
