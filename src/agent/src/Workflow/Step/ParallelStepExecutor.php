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

final class ParallelStepExecutor implements StepExecutorInterface
{
    public function supportsParallel(): bool
    {
        return true;
    }

    /**
     * @param StepInterface[] $steps
     *
     * @return ResultInterface[]
     */
    public function execute(
        array $steps,
        AgentInterface $agent,
        WorkflowStateInterface $state,
    ): array {
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
                usleep(10000); // 10ms
            }
        }

        if (!empty($exceptions)) {
            throw new \RuntimeException(\sprintf('Parallel execution failed with %d errors', \count($exceptions)), 0, reset($exceptions));
        }

        return $results;
    }
}
