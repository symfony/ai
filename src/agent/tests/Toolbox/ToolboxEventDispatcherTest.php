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

use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolCustomException;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolException;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolNoParams;
use Symfony\AI\Agent\Toolbox\Event\ToolCallArgumentsResolved;
use Symfony\AI\Agent\Toolbox\Event\ToolCallFailed;
use Symfony\AI\Agent\Toolbox\Event\ToolCallSucceeded;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class ToolboxEventDispatcherTest extends TestCase
{
    private Toolbox $toolbox;

    /**
     * @var array<string>
     */
    private array $dispatchedEvents = [];

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->method('dispatch')
            ->willReturnCallback(function (object $event, ?string $eventName = null) {
                $this->dispatchedEvents[] = $eventName ?? $event::class;

                return $event;
            });
        $this->toolbox = new Toolbox([
            new ToolNoParams(),
            new ToolException(),
            new ToolCustomException(),
        ], eventDispatcher: $dispatcher);
    }

    public function testExecuteWithUnknownTool()
    {
        try {
            $this->toolbox->execute(new ToolCall('call_1234', 'foo_bar_baz'));
        } catch (\Throwable) {
        }
        $this->assertEmpty($this->dispatchedEvents);
    }

    public function testExecuteWithToolExecutionException()
    {
        try {
            $this->toolbox->execute(new ToolCall('call_1234', 'tool_exception'));
        } catch (\Throwable) {
        }
        $this->assertSame([
            ToolCallArgumentsResolved::class,
            ToolCallFailed::class,
        ], $this->dispatchedEvents);
    }

    public function testExecuteWithCustomExecutionException()
    {
        try {
            $this->toolbox->execute(new ToolCall('call_1234', 'tool_custom_exception'));
        } catch (\Throwable) {
        }
        $this->assertSame([
            ToolCallArgumentsResolved::class,
            ToolCallFailed::class,
        ], $this->dispatchedEvents);
    }

    public function testExecuteSuccess()
    {
        try {
            $this->toolbox->execute(new ToolCall('call_1234', 'tool_no_params'));
        } catch (\Throwable) {
        }
        $this->assertSame([
            ToolCallArgumentsResolved::class,
            ToolCallSucceeded::class,
        ], $this->dispatchedEvents);
    }
}
