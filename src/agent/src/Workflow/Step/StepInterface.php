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

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
interface StepInterface
{
    public function getName(): string;

    public function execute(AgentInterface $agent, WorkflowStateInterface $state): ResultInterface;

    public function isParallel(): bool;

    public function getRetryCount(): int;

    public function getRetryDelay(): int;
}
