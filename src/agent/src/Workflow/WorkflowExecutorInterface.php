<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Workflow;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Result\ResultInterface;

interface WorkflowExecutorInterface
{
    public function execute(AgentInterface $agent, WorkflowStateInterface $state, array $options = []): ResultInterface;

    public function resume(string $id): ResultInterface;
}
