<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Workflow\Bridge;

use Symfony\AI\Agent\Workflow\Transition\TransitionInterface;
use Symfony\AI\Agent\Workflow\Transition\TransitionRegistryInterface;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\Transition as SymfonyTransition;

final class SymfonyWorkflowAdapter
{
    private StateMachine $stateMachine;

    public function __construct(
        TransitionRegistryInterface $transitionRegistry,
        string $initialPlace = 'start',
    ) {
        $this->stateMachine = $this->createStateMachine($transitionRegistry, $initialPlace);
    }

    public function can(WorkflowStateInterface $subject, string $transitionName): bool
    {
        return $this->stateMachine->can($subject, $transitionName);
    }

    public function apply(WorkflowStateInterface $subject, string $transitionName): void
    {
        $this->stateMachine->apply($subject, $transitionName);
    }

    /**
     * @return string[]
     */
    public function getEnabledTransitions(WorkflowStateInterface $subject): array
    {
        return array_map(
            static fn (SymfonyTransition $transition): string => $transition->getName(),
            $this->stateMachine->getEnabledTransitions($subject)
        );
    }

    public function getStateMachine(): StateMachine
    {
        return $this->stateMachine;
    }

    private function createStateMachine(TransitionRegistryInterface $transitionRegistry, string $initialPlace): StateMachine
    {
        $places = [$initialPlace];
        $transitions = [];

        foreach ($transitionRegistry->getTransitions() as $transition) {
            if (!$transition instanceof TransitionInterface) {
                continue;
            }

            $from = $transition->getFrom();
            $to = $transition->getTo();

            if (!\in_array($from, $places, true)) {
                $places[] = $from;
            }
            if (!\in_array($to, $places, true)) {
                $places[] = $to;
            }

            $transitions[] = new SymfonyTransition($transition->getName(), $from, $to);
        }

        $definition = new Definition($places, $transitions, $initialPlace);

        $markingStore = new MethodMarkingStore(true, 'getCurrentStep');

        return new StateMachine($definition, $markingStore);
    }
}
