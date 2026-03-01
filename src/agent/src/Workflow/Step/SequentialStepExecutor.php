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

final class SequentialStepExecutor implements StepExecutorInterface
{
    /**
     * @param StepInterface[] $steps
     *
     * @return ResultInterface[]
     */
    public function execute(array $steps, AgentInterface $agent, WorkflowStateInterface $state): array
    {
        return array_map(
            static fn (StepInterface $step): ResultInterface => $step->execute($agent, $state),
            $steps,
        );
    }
}
