<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests\Contract\Gpt;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Contract\Gpt\ToolCallNormalizer;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ToolCall;

class ToolCallNormalizerTest extends TestCase
{
    public function testNormalize()
    {
        $toolCall = new ToolCall('some-id', 'roll-die', ['sides' => 24]);

        $actual = (new ToolCallNormalizer())->normalize($toolCall, null, [Contract::CONTEXT_MODEL => new Gpt('o3')]);
        $this->assertEquals([
            'arguments' => json_encode($toolCall->getArguments()),
            'call_id' => $toolCall->getId(),
            'name' => $toolCall->getName(),
            'type' => 'function_call',
        ], $actual);
    }

    #[DataProvider('supportsNormalizationProvider')]
    public function testSupportsNormalization(mixed $data, Model $model, bool $expected)
    {
        $this->assertSame(
            $expected,
            (new ToolCallNormalizer())->supportsNormalization($data, null, [Contract::CONTEXT_MODEL => $model])
        );
    }

    public static function supportsNormalizationProvider(): \Generator
    {
        $toolCall = new ToolCall('some-id', 'roll-die', ['sides' => 24]);
        $gpt = new Gpt('o3');

        yield 'supported' => [$toolCall, $gpt, true];
        yield 'unsupported model' => [$toolCall, new Model('foo'), false];
        yield 'unsupported data' => [new Text('foo'), $gpt, false];
    }
}
