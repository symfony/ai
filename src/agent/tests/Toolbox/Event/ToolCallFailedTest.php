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
use Symfony\AI\Agent\Toolbox\Event\ToolCallFailed;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

final class ToolCallFailedTest extends TestCase
{
    private ToolCall $toolCall;
    private Tool $tool;

    protected function setUp(): void
    {
        $this->toolCall = new ToolCall('call_123', 'my_tool', ['arg' => 'value']);
        $this->tool = new Tool(new ExecutionReference(self::class, '__invoke'), 'my_tool', 'A test tool');
    }

    public function testGetTool()
    {
        $event = new ToolCallFailed($this->toolCall, $this->tool, [], new \Exception('Unexpected error!'));

        $this->assertSame($this->toolCall, $event->getTool());
    }

    public function testGetDefinition()
    {
        $event = new ToolCallFailed($this->toolCall, $this->tool, [], new \Exception('Unexpected error!'));

        $this->assertSame($this->tool, $event->getDefinition());
    }

    public function testGetArguments()
    {
        $event = new ToolCallFailed($this->toolCall, $this->tool, ['arg1' => 'value1', 'arg2' => 2], new \Exception('Unexpected error!'));

        $this->assertEqualsCanonicalizing(['arg1' => 'value1', 'arg2' => 2], $event->getArguments());
    }

    public function testGetException()
    {
        $event = new ToolCallFailed($this->toolCall, $this->tool, [], new \Exception('Unexpected error!'));

        $this->assertInstanceOf(\Exception::class, $event->getException());
        $this->assertSame('Unexpected error!', $event->getException()->getMessage());
    }
}
