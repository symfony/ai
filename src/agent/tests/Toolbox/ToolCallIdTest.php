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
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolCallAware;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\Attribute\MapToolArguments;
use Symfony\AI\Agent\Toolbox\Exception\ToolExecutionException;
use Symfony\AI\Agent\Toolbox\FiberToolExecutor;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Agent\Toolbox\ToolFactory\MemoryToolFactory;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\SomeStructure;

/**
 * @author Andrey Bolonin <andreybolonin1989@gmail.com>
 */
final class ToolCallIdTest extends TestCase
{
    public function testResolverSuppliesTheRealCallId()
    {
        $toolbox = new Toolbox([new ToolCallAware()]);
        foreach (['first', 'second'] as $id) {
            $result = $toolbox->execute(new ToolCall($id, 'call_aware', ['query' => $id, '_toolCallId' => 'spoofed']))->getResult();
            $this->assertSame(['query' => $id, 'before' => $id, 'after' => $id], $result);
        }
        $schema = $toolbox->getTools()[0]->getParameters();
        $this->assertSame(['query'], array_keys($schema['properties']));
        $this->assertSame(['query'], $schema['required']);
    }

    public function testMemoryFactoryHidesTheReservedParameter()
    {
        $tool = new ToolCallAware();
        $factory = (new MemoryToolFactory())->addTool($tool, 'call_aware', 'Return the call ID');
        $toolbox = new Toolbox([$tool], toolFactory: $factory);

        $this->assertSame(['query'], array_keys($toolbox->getTools()[0]->getParameters()['properties']));
        $this->assertSame('id', $toolbox->execute(new ToolCall('id', 'call_aware', ['query' => 'a']))->getResult()['before']);
    }

    public function testCallIdOnlyToolHasNoModelParameters()
    {
        $tool = new #[AsTool('call_id', 'Return the call ID')] class {
            public function __invoke(string $_toolCallId): string
            {
                return $_toolCallId;
            }
        };
        $toolbox = new Toolbox([$tool]);
        $this->assertNull($toolbox->getTools()[0]->getParameters());
        $this->assertSame('id', $toolbox->execute(new ToolCall('id', 'call_id'))->getResult());
    }

    public function testMappedArgumentsCanReceiveTheCallId()
    {
        $tool = new #[AsTool('mapped_call', 'Return the ingredient and call ID')] class {
            public function __invoke(#[MapToolArguments] SomeStructure $recipe, string $_toolCallId): string
            {
                return $recipe->some.':'.$_toolCallId;
            }
        };
        $toolbox = new Toolbox([$tool]);
        $schema = $toolbox->getTools()[0]->getParameters();
        $this->assertArrayNotHasKey('_toolCallId', $schema['properties']);
        $this->assertSame('flour:id', $toolbox->execute(new ToolCall('id', 'mapped_call', ['some' => 'flour']))->getResult());
    }

    public function testParallelCallsKeepTheirOwnIds()
    {
        $tool = new ToolCallAware();
        $tool->suspend = true;
        $calls = [new ToolCall('first', 'call_aware', ['query' => 'a']), new ToolCall('second', 'call_aware', ['query' => 'b'])];
        $execution = (new FiberToolExecutor(new Toolbox([$tool])))->execute($calls);
        iterator_to_array($execution);
        foreach ($execution->getReturn() as $index => $result) {
            $this->assertSame($calls[$index]->getId(), $result->getResult()['before']);
            $this->assertSame($calls[$index]->getId(), $result->getResult()['after']);
        }
    }

    public function testToolCanPassTheIdToItsOwnFiber()
    {
        $tool = new #[AsTool('fiber_call', 'Return the call ID from a Fiber')] class {
            public function __invoke(string $_toolCallId): string
            {
                $fiber = new \Fiber(static fn (): string => $_toolCallId);
                $fiber->start();

                return $fiber->getReturn();
            }
        };
        $this->assertSame('id', (new Toolbox([$tool]))->execute(new ToolCall('id', 'fiber_call'))->getResult());
    }

    public function testFailingCallDoesNotAffectTheNextId()
    {
        $tool = new ToolCallAware();
        $tool->fail = true;
        $toolbox = new Toolbox([$tool]);
        try {
            $toolbox->execute(new ToolCall('failed', 'call_aware', ['query' => 'a']));
            $this->fail('Expected tool failure.');
        } catch (ToolExecutionException) {
            $tool->fail = false;
        }
        $this->assertSame('next', $toolbox->execute(new ToolCall('next', 'call_aware', ['query' => 'b']))->getResult()['before']);
    }
}
