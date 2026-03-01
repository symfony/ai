<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Workflow\Transition;

use Symfony\AI\Agent\Workflow\WorkflowStateInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
interface TransitionInterface
{
    public function getName(): string;

    public function getFrom(): string;

    public function getTo(): string;

    public function canTransition(WorkflowStateInterface $state): bool;

    public function beforeTransition(WorkflowStateInterface $state): void;

    public function afterTransition(WorkflowStateInterface $state): void;
}
