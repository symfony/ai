<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Profiler;

use Symfony\AI\Agent\Workflow\AgentWorkflowInterface;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 *
 * @phpstan-type AgentWorkflowData array{
 *     action: string,
 *     initial_state?: WorkflowStateInterface,
 *     id?: string,
 *     called_at: \DateTimeImmutable,
 * }
 */
final class TraceableAgentWorkflow implements AgentWorkflowInterface, ResetInterface
{
    /**
     * @var AgentWorkflowData[]
     */
    public array $calls = [];

    public function __construct(
        private readonly AgentWorkflowInterface $agentWorkflow,
        private readonly ClockInterface $clock = new MonotonicClock(),
    ) {
    }

    public function run(WorkflowStateInterface $initialState): WorkflowStateInterface
    {
        $this->calls[] = [
            'action' => 'run',
            'initial_state' => $initialState,
            'called_at' => $this->clock->now(),
        ];

        return $this->agentWorkflow->run($initialState);
    }

    public function resume(string $id): WorkflowStateInterface
    {
        $this->calls[] = [
            'action' => 'resume',
            'id' => $id,
            'called_at' => $this->clock->now(),
        ];

        return $this->agentWorkflow->resume($id);
    }

    public function reset(): void
    {
        $this->calls = [];
    }
}
