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
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
abstract class WorkflowEvent extends Event implements WorkflowEventInterface
{
    private readonly \DateTimeInterface $occurredAt;

    public function __construct(
        private readonly WorkflowStateInterface $state,
        ClockInterface $clock = new MonotonicClock(),
    ) {
        $this->occurredAt = $clock->now();
    }

    public function getState(): WorkflowStateInterface
    {
        return $this->state;
    }

    public function getOccurredAt(): \DateTimeInterface
    {
        return $this->occurredAt;
    }
}
