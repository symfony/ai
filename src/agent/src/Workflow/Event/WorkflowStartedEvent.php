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
 * Dispatched when a workflow run starts, before any place is executed.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class WorkflowStartedEvent
{
    public function __construct(
        private readonly WorkflowStateInterface $state,
        private readonly bool $resume = false,
    ) {
    }

    public function getState(): WorkflowStateInterface
    {
        return $this->state;
    }

    public function isResume(): bool
    {
        return $this->resume;
    }
}
