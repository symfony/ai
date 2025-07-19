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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\Exception\ToolExecutionException;
use Symfony\AI\Agent\Toolbox\Exception\ToolNotFoundException;
use Symfony\AI\Agent\Toolbox\FaultTolerantToolbox;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Fixtures\Tool\ToolNoParams;
use Symfony\AI\Fixtures\Tool\ToolRequiredParams;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

#[CoversClass(FaultTolerantToolbox::class)]
#[UsesClass(ToolCall::class)]
#[UsesClass(Tool::class)]
#[UsesClass(ExecutionReference::class)]
#[UsesClass(ToolNotFoundException::class)]
#[UsesClass(ToolExecutionException::class)]
final class FaultTolerantToolboxTest extends TestCase
{
    #[Test]
    public function faultyToolExecution(): void
    {
        $faultyToolbox = $this->createFaultyToolbox(
            fn (ToolCall $toolCall) => ToolExecutionException::executionFailed($toolCall, new \Exception('error'))
        );

        $faultTolerantToolbox = new FaultTolerantToolbox($faultyToolbox);
        $expected = 'An error occurred while executing tool "tool_foo".';

        $toolCall = new ToolCall('987654321', 'tool_foo');
        $actual = $faultTolerantToolbox->execute($toolCall);

        self::assertSame($expected, $actual);
    }

    #[Test]
    public function faultyToolCall(): void
    {
        $faultyToolbox = $this->createFaultyToolbox(
            fn (ToolCall $toolCall) => ToolNotFoundException::notFoundForToolCall($toolCall)
        );

        $faultTolerantToolbox = new FaultTolerantToolbox($faultyToolbox);
        $expected = 'Tool "tool_xyz" was not found, please use one of these: tool_no_params, tool_required_params';

        $toolCall = new ToolCall('123456789', 'tool_xyz');
        $actual = $faultTolerantToolbox->execute($toolCall);

        self::assertSame($expected, $actual);
    }

    private function createFaultyToolbox(\Closure $exceptionFactory): ToolboxInterface
    {
        return new class($exceptionFactory) implements ToolboxInterface {
            public function __construct(private readonly \Closure $exceptionFactory)
            {
            }

            /**
             * @return Tool[]
             */
            public function getTools(): array
            {
                return [
                    new Tool(new ExecutionReference(ToolNoParams::class), 'tool_no_params', 'A tool without parameters', null),
                    new Tool(new ExecutionReference(ToolRequiredParams::class, 'bar'), 'tool_required_params', 'A tool with required parameters', null),
                ];
            }

            public function execute(ToolCall $toolCall): mixed
            {
                throw ($this->exceptionFactory)($toolCall);
            }
        };
    }
}
