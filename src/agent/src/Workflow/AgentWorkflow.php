<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Workflow;

use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Exception\WorkflowGuardException;
use Symfony\AI\Agent\Workflow\TransitionResolver\StateBasedTransitionResolver;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Orchestrates workflow execution by running executors at each place
 * and using the Symfony Workflow component for transition logic.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class AgentWorkflow implements AgentWorkflowInterface
{
    /**
     * @param array<non-empty-string, ExecutorInterface> $executors Map of place name to executor
     * @param array<non-empty-string, GuardInterface[]>  $guards    Map of place name to guards
     */
    public function __construct(
        private readonly WorkflowInterface $workflow,
        private readonly array $executors,
        private readonly WorkflowStateStoreInterface $store,
        private readonly TransitionResolverInterface $transitionResolver = new StateBasedTransitionResolver(),
        private readonly array $guards = [],
    ) {
    }

    public function run(WorkflowStateInterface $initialState): WorkflowStateInterface
    {
        $subject = new \stdClass();
        $subject->marking = null;

        return $this->doRun($initialState, $subject);
    }

    public function resume(string $id): WorkflowStateInterface
    {
        if (!$this->store->has($id)) {
            throw new InvalidArgumentException(\sprintf('Workflow with ID "%s" not found.', $id));
        }

        $state = $this->store->load($id);

        $subject = new \stdClass();
        $completedPlaces = $state->getCompletedPlaces();

        $subject->marking = [] === $completedPlaces ? null : end($completedPlaces);

        return $this->doRun($state, $subject);
    }

    private function doRun(WorkflowStateInterface $state, object $subject): WorkflowStateInterface
    {
        $this->store->save($state);

        $marking = $this->workflow->getMarking($subject);

        while (true) {
            $places = array_keys($marking->getPlaces());

            foreach ($places as $place) {
                if (!isset($this->executors[$place])) {
                    throw new InvalidArgumentException(\sprintf('No executor registered for place "%s".', $place));
                }

                $state = $state->withCurrentPlace($place);

                if (isset($this->guards[$place])) {
                    foreach ($this->guards[$place] as $guard) {
                        if (!$guard->execute($state, $place)) {
                            throw new WorkflowGuardException(\sprintf('Guard rejected execution at place "%s".', $place));
                        }
                    }
                }

                $state = $this->executors[$place]->execute($state, $place);
                $state = $state->withCompletedPlace($place);

                $this->store->save($state);
            }

            $transitionName = $this->transitionResolver->resolve($state, $places[0], $this->workflow, $subject);

            if (null === $transitionName) {
                break;
            }

            $state = $state->unset('_next_transition');
            $marking = $this->workflow->apply($subject, $transitionName);
        }

        $state = $state->withCurrentPlace(null);

        $this->store->save($state);

        return $state;
    }
}
