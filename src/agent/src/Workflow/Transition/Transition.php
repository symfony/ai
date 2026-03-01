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
final class Transition implements TransitionInterface
{
    /**
     * @param \Closure[] $guards
     */
    public function __construct(
        private readonly string $name,
        private readonly string $from,
        private readonly string $to,
        private readonly array $guards = [],
        private readonly ?\Closure $beforeCallback = null,
        private readonly ?\Closure $afterCallback = null,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getFrom(): string
    {
        return $this->from;
    }

    public function getTo(): string
    {
        return $this->to;
    }

    public function canTransition(WorkflowStateInterface $state): bool
    {
        if ($state->getCurrentStep() !== $this->from) {
            return false;
        }

        if ([] === $this->guards) {
            return true;
        }

        foreach ($this->guards as $guard) {
            if (!$guard($state)) {
                return false;
            }
        }

        return true;
    }

    public function beforeTransition(WorkflowStateInterface $state): void
    {
        if (null !== $this->beforeCallback) {
            ($this->beforeCallback)($state);
        }
    }

    public function afterTransition(WorkflowStateInterface $state): void
    {
        $state->setCurrentStep($this->to);

        if (null !== $this->afterCallback) {
            ($this->afterCallback)($state);
        }
    }
}
