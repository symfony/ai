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

use Symfony\AI\Agent\Workflow\Transition\TransitionInterface;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class TransitionEvent extends WorkflowEvent
{
    public function __construct(
        WorkflowStateInterface $state,
        private readonly TransitionInterface $transition,
    ) {
        parent::__construct($state);
    }

    public function getTransition(): TransitionInterface
    {
        return $this->transition;
    }

    public function getEventName(): string
    {
        return 'workflow.transition';
    }
}
