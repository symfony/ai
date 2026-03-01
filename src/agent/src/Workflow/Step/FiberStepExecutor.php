<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Workflow\Step;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;

final class FiberStepExecutor implements StepExecutorInterface
{
    public function __construct(
        private readonly ClockInterface $clock = new MonotonicClock(),
    ) {
    }

    /**
     * @param StepInterface[] $steps
     *
     * @return ResultInterface[]
     */
    public function execute(array $steps, AgentInterface $agent, WorkflowStateInterface $state): array
    {
        $fibers = [];
        $results = [];
        $exceptions = [];

        foreach ($steps as $index => $step) {
            $fibers[$index] = new \Fiber(static fn (): ResultInterface => $step->execute($agent, $state));
        }

        foreach ($fibers as $index => $fiber) {
            try {
                $fiber->start();
            } catch (\Throwable $e) {
                $exceptions[$index] = $e;
            }
        }

        $completed = [];
        while (\count($completed) < \count($fibers)) {
            foreach ($fibers as $index => $fiber) {
                if (isset($completed[$index]) || isset($exceptions[$index])) {
                    continue;
                }

                try {
                    if ($fiber->isTerminated()) {
                        $results[$index] = $fiber->getReturn();
                        $completed[$index] = true;
                    } elseif ($fiber->isSuspended()) {
                        $fiber->resume();
                    }
                } catch (\Throwable $e) {
                    $exceptions[$index] = $e;
                    $completed[$index] = true;
                }
            }

            if (\count($completed) < \count($fibers)) {
                $this->clock->sleep(0.01);
            }
        }

        if ([] !== $exceptions) {
            throw new \RuntimeException(\sprintf('Fibers execution failed with %d errors', \count($exceptions)), 0, reset($exceptions));
        }

        return $results;
    }
}
