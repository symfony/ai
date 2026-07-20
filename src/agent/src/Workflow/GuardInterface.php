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
 * A guard declares the places it applies to through {@see supports()}; the
 * engine only calls {@see allows()} for those places.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
interface GuardInterface
{
    /**
     * Whether this guard applies to the given place.
     *
     * @param non-empty-string $place
     */
    public function supports(string $place): bool;

    /**
     * Whether the place is allowed to execute.
     *
     * @param non-empty-string $place The place about to be executed
     */
    public function allows(WorkflowStateInterface $state, string $place): bool;
}
