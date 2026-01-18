<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Toolbox;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\Source\Source;
use Symfony\AI\Agent\Toolbox\Source\SourceCollection;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Result\ToolCall;

final class ToolResultTest extends TestCase
{
    public function testGetToolCall()
    {
        $toolCall = new ToolCall('123', 'my_tool', ['arg' => 'value']);
        $toolResult = new ToolResult($toolCall, 'some result');

        $this->assertSame($toolCall, $toolResult->getToolCall());
    }

    public function testGetResult()
    {
        $toolCall = new ToolCall('123', 'my_tool');
        $result = ['key' => 'value', 'number' => 42];
        $toolResult = new ToolResult($toolCall, $result);

        $this->assertSame($result, $toolResult->getResult());
    }

    public function testGetSourcesWithoutSourceCollection()
    {
        $toolCall = new ToolCall('123', 'my_tool');
        $toolResult = new ToolResult($toolCall, 'result');

        $this->assertSame([], $toolResult->getSources());
    }

    public function testGetSourcesWithEmptySourceCollection()
    {
        $toolCall = new ToolCall('123', 'my_tool');
        $sourceCollection = new SourceCollection();
        $toolResult = new ToolResult($toolCall, 'result', $sourceCollection);

        $this->assertSame([], $toolResult->getSources());
    }

    public function testGetSourcesWithPopulatedSourceCollection()
    {
        $toolCall = new ToolCall('123', 'my_tool');
        $source1 = new Source('doc1', 'ref1', 'content1');
        $source2 = new Source('doc2', 'ref2', 'content2');
        $sourceCollection = new SourceCollection([$source1, $source2]);
        $toolResult = new ToolResult($toolCall, 'result', $sourceCollection);

        $sources = $toolResult->getSources();

        $this->assertCount(2, $sources);
        $this->assertSame($source1, $sources[0]);
        $this->assertSame($source2, $sources[1]);
    }
}
