<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests\Contract\Gpt\Message;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Contract\Gpt\Message\ToolCallMessageNormalizer;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ToolCall;

class ToolCallMessageNormalizerTest extends TestCase
{
    public function testNormalize()
    {
        $toolCall = new ToolCall('some-id', 'roll-die', ['sides' => 24]);
        $toolCallMessage = new ToolCallMessage($toolCall, 'Critical hit!');

        $actual = (new ToolCallMessageNormalizer())->normalize($toolCallMessage, null, [Contract::CONTEXT_MODEL => new Gpt('o3')]);
        $this->assertEquals([
            'type' => 'function_call_output',
            'call_id' => $toolCall->getId(),
            'output' => $toolCallMessage->getContent(),
        ], $actual);
    }

    #[DataProvider('supportsNormalizationProvider')]
    public function testSupportsNormalization(mixed $data, Model $model, bool $expected)
    {
        $this->assertSame(
            $expected,
            (new ToolCallMessageNormalizer())->supportsNormalization($data, null, [Contract::CONTEXT_MODEL => $model])
        );
    }

    public static function supportsNormalizationProvider(): \Generator
    {
        $toolCallMessage = new ToolCallMessage(
            new ToolCall('some-id', 'roll-die', ['sides' => 24]),
            'Critical hit!'
        );
        $gpt = new Gpt('o3');

        yield 'supported' => [$toolCallMessage, $gpt, true];
        yield 'unsupported model' => [$toolCallMessage, new Model('foo'), false];
        yield 'unsupported data' => [new Text('foo'), $gpt, false];
    }
}
