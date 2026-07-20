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
 * Dispatched before a place executor runs, once its guards have passed.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class PlaceEnteredEvent
{
    /**
     * @param non-empty-string $place
     */
    public function __construct(
        private readonly WorkflowStateInterface $state,
        private readonly string $place,
    ) {
    }

    public function getState(): WorkflowStateInterface
    {
        return $this->state;
    }

    /**
     * @return non-empty-string
     */
    public function getPlace(): string
    {
        return $this->place;
    }
}
