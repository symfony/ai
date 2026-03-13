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

/**
 * Checks whether a workflow place is allowed to execute.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
interface GuardInterface
{
    /**
     * @param non-empty-string $place The place about to be executed
     */
    public function execute(WorkflowStateInterface $state, string $place): bool;
}
