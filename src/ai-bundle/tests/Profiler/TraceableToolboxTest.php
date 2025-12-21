<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Tests\Profiler;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\AiBundle\Profiler\TraceableToolbox;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

final class TraceableToolboxTest extends TestCase
{
    public function testGetMap()
    {
        $metadata = new Tool(new ExecutionReference('Foo\Bar'), 'bar', 'description', null);
        $toolbox = $this->createToolbox(['tool' => $metadata]);
        $traceableToolbox = new TraceableToolbox($toolbox);

        $map = $traceableToolbox->getTools();

        $this->assertSame(['tool' => $metadata], $map);
    }

    public function testExecute()
    {
        $metadata = new Tool(new ExecutionReference('Foo\Bar'), 'bar', 'description', null);
        $toolbox = $this->createToolbox(['tool' => $metadata]);
        $traceableToolbox = new TraceableToolbox($toolbox);
        $toolCall = new ToolCall('foo', '__invoke');

        $result = $traceableToolbox->execute($toolCall);

        $this->assertSame('tool_result', $result->getResult());
        $this->assertCount(1, $traceableToolbox->calls);
        $this->assertSame($toolCall, $traceableToolbox->calls[0]->getToolCall());
        $this->assertSame('tool_result', $traceableToolbox->calls[0]->getResult());
    }

    /**
     * @param Tool[] $tools
     */
    private function createToolbox(array $tools): ToolboxInterface
    {
        return new class($tools) implements ToolboxInterface {
            /**
             * @param Tool[] $tools
             */
            public function __construct(
                private readonly array $tools,
            ) {
            }

            public function getTools(): array
            {
                return $this->tools;
            }

            public function execute(ToolCall $toolCall): ToolResult
            {
                return new ToolResult($toolCall, 'tool_result');
            }
        };
    }
}
