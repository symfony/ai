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

use Symfony\AI\Agent\Exception\TransitionResolutionException;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Determines which transition to apply after executing a place.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
interface TransitionResolverInterface
{
    /**
     * Resolve the next transition to apply.
     *
     * Returns null when the workflow has reached a final place (no transitions available).
     *
     * @param non-empty-string $currentPlace
     *
     * @throws TransitionResolutionException When no valid transition can be determined
     */
    public function resolve(WorkflowStateInterface $state, string $currentPlace, WorkflowInterface $workflow, object $subject): ?string;
}
