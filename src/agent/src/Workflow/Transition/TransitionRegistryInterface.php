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
interface TransitionRegistryInterface
{
    public function addTransition(TransitionInterface $transition): void;

    public function getTransition(string $name): ?TransitionInterface;

    public function getAvailableTransitions(WorkflowStateInterface $state): array;

    public function canTransition(string $name, WorkflowStateInterface $state): bool;

    public function getTransitions(): array;
}
