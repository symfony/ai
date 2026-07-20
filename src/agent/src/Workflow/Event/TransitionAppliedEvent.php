<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Workflow\Event;

use Symfony\AI\Agent\Workflow\WorkflowStateInterface;

/**
 * Dispatched after a transition has been applied to move between places.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class TransitionAppliedEvent
{
    /**
     * @param non-empty-string $transition
     */
    public function __construct(
        private readonly WorkflowStateInterface $state,
        private readonly string $transition,
    ) {
    }

    public function getState(): WorkflowStateInterface
    {
        return $this->state;
    }

    /**
     * @return non-empty-string
     */
    public function getTransition(): string
    {
        return $this->transition;
    }
}
