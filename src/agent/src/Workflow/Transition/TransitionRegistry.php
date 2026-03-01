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
final class TransitionRegistry implements TransitionRegistryInterface
{
    /** @var TransitionInterface[] */
    private array $transitions = [];

    public function addTransition(TransitionInterface $transition): void
    {
        $this->transitions[$transition->getName()] = $transition;
    }

    public function getTransition(string $name): ?TransitionInterface
    {
        return $this->transitions[$name] ?? null;
    }

    /**
     * @return TransitionInterface[]
     */
    public function getAvailableTransitions(WorkflowStateInterface $state): array
    {
        return array_filter(
            $this->transitions,
            static fn (TransitionInterface $transition): bool => $transition->canTransition($state)
        );
    }

    public function canTransition(string $name, WorkflowStateInterface $state): bool
    {
        $transition = $this->getTransition($name);

        return $transition && $transition->canTransition($state);
    }

    /**
     * @return TransitionInterface[]
     */
    public function getTransitions(): array
    {
        return $this->transitions;
    }
}
