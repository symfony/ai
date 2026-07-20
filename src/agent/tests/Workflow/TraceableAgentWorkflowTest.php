<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Workflow;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Workflow\AgentWorkflowInterface;
use Symfony\AI\Agent\Workflow\TraceableAgentWorkflow;
use Symfony\AI\Agent\Workflow\WorkflowState;
use Symfony\Component\Clock\MockClock;

final class TraceableAgentWorkflowTest extends TestCase
{
    public function testRunDelegatesToInnerAndRecords()
    {
        $clock = new MockClock('2025-01-01 00:00:00');
        $initialState = new WorkflowState('wf-1');
        $resultState = new WorkflowState('wf-1', ['result' => 'done']);

        $inner = $this->createMock(AgentWorkflowInterface::class);
        $inner->expects($this->once())
            ->method('run')
            ->with($initialState)
            ->willReturn($resultState);

        $traceable = new TraceableAgentWorkflow($inner, $clock);
        $result = $traceable->run($initialState);

        $this->assertSame($resultState, $result);
        $this->assertCount(1, $traceable->getCalls());
        $this->assertSame('run', $traceable->getCalls()[0]['action']);
        $this->assertSame($initialState, $traceable->getCalls()[0]['state']);
        $this->assertEquals($clock->now(), $traceable->getCalls()[0]['called_at']);
    }

    public function testResumeDelegatesToInnerAndRecords()
    {
        $clock = new MockClock('2025-01-01 00:00:00');
        $resultState = new WorkflowState('wf-1', ['result' => 'resumed']);

        $inner = $this->createMock(AgentWorkflowInterface::class);
        $inner->expects($this->once())
            ->method('resume')
            ->with('wf-1')
            ->willReturn($resultState);

        $traceable = new TraceableAgentWorkflow($inner, $clock);
        $result = $traceable->resume('wf-1');

        $this->assertSame($resultState, $result);
        $this->assertCount(1, $traceable->getCalls());
        $this->assertSame('resume', $traceable->getCalls()[0]['action']);
        $this->assertSame('wf-1', $traceable->getCalls()[0]['id']);
        $this->assertEquals($clock->now(), $traceable->getCalls()[0]['called_at']);
    }

    public function testReset()
    {
        $inner = $this->createMock(AgentWorkflowInterface::class);
        $inner->method('run')->willReturn(new WorkflowState('wf-1'));

        $traceable = new TraceableAgentWorkflow($inner);
        $traceable->run(new WorkflowState('wf-1'));

        $this->assertCount(1, $traceable->getCalls());

        $traceable->reset();

        $this->assertSame([], $traceable->getCalls());
    }
}
