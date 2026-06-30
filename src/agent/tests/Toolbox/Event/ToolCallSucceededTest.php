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
use Symfony\AI\Agent\Toolbox\Event\ToolCallSucceeded;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

final class ToolCallSucceededTest extends TestCase
{
    private ToolCall $toolCall;
    private Tool $metadata;

    protected function setUp(): void
    {
        $this->toolCall = new ToolCall('call_123', 'my_tool', ['arg' => 'value']);
        $this->metadata = new Tool(new ExecutionReference(self::class, '__invoke'), 'my_tool', 'A test tool');
    }

    public function testGetTool()
    {
        $event = new ToolCallSucceeded($this->toolCall, $this->metadata, [], new ToolResult($this->toolCall, 'Result here!'));

        $this->assertSame($this->toolCall, $event->getTool());
    }

    public function testGetMetadata()
    {
        $event = new ToolCallSucceeded($this->toolCall, $this->metadata, [], new ToolResult($this->toolCall, 'Result here!'));

        $this->assertSame($this->metadata, $event->getMetadata());
    }

    public function testGetArguments()
    {
        $event = new ToolCallSucceeded($this->toolCall, $this->metadata, ['arg1', 'arg2'], new ToolResult($this->toolCall, 'Result here!'));

        $this->assertEqualsCanonicalizing(['arg1', 'arg2'], $event->getArguments());
    }

    public function testGetResult()
    {
        $event = new ToolCallSucceeded($this->toolCall, $this->metadata, [], new ToolResult($this->toolCall, 'Result here!'));

        $this->assertInstanceOf(ToolResult::class, $event->getResult());
        $this->assertSame('Result here!', $event->getResult()->getResult());
    }
}
