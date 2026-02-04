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
use Symfony\AI\Agent\Toolbox\Event\ToolCallRequested;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

final class ToolCallRequestedTest extends TestCase
{
    private ToolCall $toolCall;
    private Tool $metadata;

    protected function setUp(): void
    {
        $this->toolCall = new ToolCall('call_123', 'my_tool', ['arg' => 'value']);
        $this->metadata = new Tool(new ExecutionReference(self::class, '__invoke'), 'my_tool', 'A test tool');
    }

    public function testGetToolCall()
    {
        $event = new ToolCallRequested($this->toolCall, $this->metadata);

        $this->assertSame($this->toolCall, $event->getToolCall());
    }

    public function testGetMetadata()
    {
        $event = new ToolCallRequested($this->toolCall, $this->metadata);

        $this->assertSame($this->metadata, $event->getMetadata());
    }

    public function testInitialStateIsNotDenied()
    {
        $event = new ToolCallRequested($this->toolCall, $this->metadata);

        $this->assertFalse($event->isDenied());
        $this->assertNull($event->getDenialReason());
    }

    public function testDeny()
    {
        $event = new ToolCallRequested($this->toolCall, $this->metadata);

        $event->deny('Not allowed');

        $this->assertTrue($event->isDenied());
        $this->assertSame('Not allowed', $event->getDenialReason());
    }

    public function testInitialStateHasNoResult()
    {
        $event = new ToolCallRequested($this->toolCall, $this->metadata);

        $this->assertFalse($event->hasResult());
        $this->assertNull($event->getResult());
    }

    public function testSetResult()
    {
        $event = new ToolCallRequested($this->toolCall, $this->metadata);
        $result = new ToolResult($this->toolCall, 'custom result');

        $event->setResult($result);

        $this->assertTrue($event->hasResult());
        $this->assertSame($result, $event->getResult());
    }

    public function testPropagationNotStoppedInitially()
    {
        $event = new ToolCallRequested($this->toolCall, $this->metadata);

        $this->assertFalse($event->isPropagationStopped());
    }

    public function testPropagationStoppedWhenDenied()
    {
        $event = new ToolCallRequested($this->toolCall, $this->metadata);

        $event->deny('Denied');

        $this->assertTrue($event->isPropagationStopped());
    }

    public function testPropagationStoppedWhenResultSet()
    {
        $event = new ToolCallRequested($this->toolCall, $this->metadata);

        $event->setResult(new ToolResult($this->toolCall, 'result'));

        $this->assertTrue($event->isPropagationStopped());
    }
}
