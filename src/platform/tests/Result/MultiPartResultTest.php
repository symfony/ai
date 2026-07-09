<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Result;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ThinkingResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;

final class MultiPartResultTest extends TestCase
{
    public function testAsTextFlattensOnlyTextParts()
    {
        $result = new MultiPartResult([
            new ThinkingResult('Let me think.', 'sig_abc'),
            new TextResult('Hello '),
            new TextResult('world.'),
        ]);

        $this->assertSame('Hello world.', $result->asText());
    }

    public function testAsToolCallResultReturnsNullWithoutToolCallParts()
    {
        $result = new MultiPartResult([
            new ThinkingResult('Let me think.', 'sig_abc'),
            new TextResult('Hello world.'),
        ]);

        $this->assertNull($result->asToolCallResult());
    }

    public function testAsToolCallResultAggregatesSeveralToolCallParts()
    {
        $toolCall1 = new ToolCall('id1', 'tool1', ['arg1' => 'value1']);
        $toolCall2 = new ToolCall('id2', 'tool2', ['arg2' => 'value2']);

        $result = new MultiPartResult([
            new ThinkingResult('Let me think.', 'sig_abc'),
            new TextResult('Looking both up.'),
            new ToolCallResult([$toolCall1]),
            new ToolCallResult([$toolCall2]),
        ]);

        $toolCallResult = $result->asToolCallResult();

        $this->assertInstanceOf(ToolCallResult::class, $toolCallResult);
        $this->assertSame([$toolCall1, $toolCall2], $toolCallResult->getContent());
    }

    public function testAsToolCallResultKeepsAlreadyAggregatedToolCalls()
    {
        $toolCall1 = new ToolCall('id1', 'tool1', ['arg1' => 'value1']);
        $toolCall2 = new ToolCall('id2', 'tool2', ['arg2' => 'value2']);

        $result = new MultiPartResult([
            new TextResult('Looking both up.'),
            new ToolCallResult([$toolCall1, $toolCall2]),
        ]);

        $toolCallResult = $result->asToolCallResult();

        $this->assertInstanceOf(ToolCallResult::class, $toolCallResult);
        $this->assertSame([$toolCall1, $toolCall2], $toolCallResult->getContent());
    }

    public function testAsToolCallResultReturnsListWithGaplessKeys()
    {
        $toolCall1 = new ToolCall('id1', 'tool1', ['arg1' => 'value1']);
        $toolCall2 = new ToolCall('id2', 'tool2', ['arg2' => 'value2']);

        $result = new MultiPartResult([
            new ToolCallResult([$toolCall1]),
            new TextResult('Interleaved text.'),
            new ToolCallResult([$toolCall2]),
        ]);

        $toolCalls = $result->asToolCallResult()->getContent();

        $this->assertSame([0, 1], array_keys($toolCalls));
    }
}
