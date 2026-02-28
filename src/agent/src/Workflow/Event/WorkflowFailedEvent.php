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
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class WorkflowFailedEvent extends WorkflowEvent
{
    public function __construct(
        WorkflowStateInterface $state,
        private readonly \Throwable $exception,
    ) {
        parent::__construct($state);
    }

    public function getException(): \Throwable
    {
        return $this->exception;
    }

    public function getEventName(): string
    {
        return 'workflow.failed';
    }
}
