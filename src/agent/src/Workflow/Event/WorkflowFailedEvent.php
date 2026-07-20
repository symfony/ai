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
 * Dispatched when a guard rejects a place, an executor throws, or the workflow
 * otherwise aborts.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class WorkflowFailedEvent
{
    /**
     * @param non-empty-string|null $place The place that failed, or null when the failure is not tied to a place
     */
    public function __construct(
        private readonly WorkflowStateInterface $state,
        private readonly ?string $place,
        private readonly \Throwable $error,
    ) {
    }

    public function getState(): WorkflowStateInterface
    {
        return $this->state;
    }

    /**
     * @return non-empty-string|null
     */
    public function getPlace(): ?string
    {
        return $this->place;
    }

    public function getError(): \Throwable
    {
        return $this->error;
    }
}
