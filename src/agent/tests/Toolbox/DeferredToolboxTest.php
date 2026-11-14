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
use Symfony\AI\Agent\Toolbox\DeferredToolbox;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Contracts\Service\ResetInterface;

final class DeferredToolboxTest extends TestCase
{
    public function testDefersAllToolsByDefault()
    {
        $toolbox = new DeferredToolbox($this->createInnerToolbox());

        $this->assertSame([true, true], array_map(static fn (Tool $tool): bool => $tool->isDeferred(), $toolbox->getTools()));
    }

    public function testDefersOnlyTheGivenTools()
    {
        $toolbox = new DeferredToolbox($this->createInnerToolbox(), ['get_invoice']);

        $deferred = [];
        foreach ($toolbox->getTools() as $tool) {
            $deferred[$tool->getName()] = $tool->isDeferred();
        }

        $this->assertSame(['clock' => false, 'get_invoice' => true], $deferred);
    }

    public function testExecutionIsDelegated()
    {
        $toolbox = new DeferredToolbox($this->createInnerToolbox());

        $result = $toolbox->execute($toolCall = new ToolCall('id', 'clock'));

        $this->assertSame('executed clock', $result->getResult());
        $this->assertSame($toolCall, $result->getToolCall());
    }

    public function testResetIsForwardedToTheInnerToolbox()
    {
        $inner = new class implements ToolboxInterface, ResetInterface {
            public bool $wasReset = false;

            public function getTools(): array
            {
                return [];
            }

            public function execute(ToolCall $toolCall): ToolResult
            {
                return new ToolResult($toolCall, '');
            }

            public function reset(): void
            {
                $this->wasReset = true;
            }
        };

        (new DeferredToolbox($inner))->reset();

        $this->assertTrue($inner->wasReset);
    }

    private function createInnerToolbox(): ToolboxInterface
    {
        return new class implements ToolboxInterface {
            public function getTools(): array
            {
                return [
                    new Tool(new ExecutionReference('Clock'), 'clock', 'Get the time'),
                    new Tool(new ExecutionReference('Invoice'), 'get_invoice', 'Fetch an invoice'),
                ];
            }

            public function execute(ToolCall $toolCall): ToolResult
            {
                return new ToolResult($toolCall, 'executed '.$toolCall->getName());
            }
        };
    }
}
