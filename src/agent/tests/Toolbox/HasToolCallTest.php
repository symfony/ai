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
use Symfony\AI\Agent\Toolbox\Event\ToolCallRequested;
use Symfony\AI\Agent\Toolbox\Exception\ToolExecutionException;
use Symfony\AI\Agent\Toolbox\FiberToolExecutor;
use Symfony\AI\Agent\Toolbox\Toolbox;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @author Andrey Bolonin <andreybolonin1989@gmail.com>
 */
final class HasToolCallTest extends TestCase
{
    public function testToolReceivesEachCallAndContextIsClearedAfterwards()
    {
        $tool = new ToolCallAware();
        $toolbox = new Toolbox([$tool]);
        $this->assertNull($tool->getToolCall());

        foreach (['first', 'second'] as $id) {
            $call = new ToolCall($id, 'call_aware', ['query' => $id]);
            $result = $toolbox->execute($call)->getResult();

            $this->assertSame($id, $result['query']);
            $this->assertSame($call, $result['before']);
            $this->assertSame($call, $result['after']);
            $this->assertNull($tool->getToolCall());
        }
    }

    public function testToolCallDoesNotBecomeAModelArgument()
    {
        $tools = (new Toolbox([new ToolCallAware()]))->getTools();

        $this->assertSame(['query'], array_keys($tools[0]->getParameters()['properties']));
    }

    public function testPreviousContextIsRestored()
    {
        $tool = new ToolCallAware();
        $previous = new ToolCall('outer', 'call_aware');
        $tool->setToolCall($previous);

        $call = new ToolCall('inner', 'call_aware', ['query' => 'nested']);
        $result = (new Toolbox([$tool]))->execute($call)->getResult();

        $this->assertSame($call, $result['after']);
        $this->assertSame($previous, $tool->getToolCall());
    }

    public function testContextIsRestoredWhenToolFails()
    {
        $tool = new ToolCallAware();
        $tool->fail = true;
        $previous = new ToolCall('outer', 'call_aware');
        $tool->setToolCall($previous);

        $this->expectException(ToolExecutionException::class);
        try {
            (new Toolbox([$tool]))->execute(new ToolCall('inner', 'call_aware', ['query' => 'fail']));
        } finally {
            $this->assertSame($previous, $tool->getToolCall());
        }
    }

    public function testDeniedCallDoesNotSetContext()
    {
        $tool = new ToolCallAware();
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ToolCallRequested::class, static function (ToolCallRequested $event): void {
            $event->deny('Denied.');
        });

        (new Toolbox([$tool], eventDispatcher: $dispatcher))->execute(new ToolCall('denied', 'call_aware', ['query' => 'denied']));

        $this->assertNull($tool->getToolCall());
    }

    public function testConcurrentCallsOnOneToolKeepTheirOwnContext()
    {
        $tool = new ToolCallAware();
        $tool->suspend = true;
        $outer = new ToolCall('outer', 'call_aware');
        $tool->setToolCall($outer);
        $calls = [
            new ToolCall('first', 'call_aware', ['query' => 'first']),
            new ToolCall('second', 'call_aware', ['query' => 'second']),
        ];
        $execution = (new FiberToolExecutor(new Toolbox([$tool])))->execute($calls);
        iterator_to_array($execution);
        $results = $execution->getReturn();

        foreach ($calls as $index => $call) {
            $this->assertSame($call, $results[$index]->getResult()['before']);
            $this->assertSame($call, $results[$index]->getResult()['after']);
        }
        $this->assertSame($outer, $tool->getToolCall());
    }
}
