<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Toolbox\Event;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\Event\ToolCallsExecuted;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;

final class ToolCallsExecutedTest extends TestCase
{
    public function testGetToolResults()
    {
        $toolResults = [
            new ToolResult(new ToolCall('tool1', 'foo'), 'result1'),
            new ToolResult(new ToolCall('tool2', 'bar'), 'result2'),
        ];

        $event = new ToolCallsExecuted($toolResults);

        $this->assertSame($toolResults, $event->getToolResults());
    }

    public function testNoResultInitially()
    {
        $event = new ToolCallsExecuted([]);

        $this->assertFalse($event->hasResult());
    }

    public function testSetAndGetResult()
    {
        $event = new ToolCallsExecuted([]);
        $result = new TextResult('The quick brown fox jumps over the lazy dog');

        $event->setResult($result);

        $this->assertTrue($event->hasResult());
        $this->assertSame($result, $event->getResult());
    }
}
