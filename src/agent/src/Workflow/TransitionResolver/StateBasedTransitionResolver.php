<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Workflow\TransitionResolver;

use Symfony\AI\Agent\Exception\TransitionResolutionException;
use Symfony\AI\Agent\Workflow\TransitionResolverInterface;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Resolves the next transition by checking the '_next_transition' key in state.
 *
 * If not set, picks the single enabled transition. If multiple transitions
 * are enabled and no hint is in state, throws.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class StateBasedTransitionResolver implements TransitionResolverInterface
{
    public function resolve(WorkflowStateInterface $state, string $currentPlace, WorkflowInterface $workflow, object $subject): ?string
    {
        $enabledTransitions = $workflow->getEnabledTransitions($subject);

        if ([] === $enabledTransitions) {
            return null;
        }

        $nextTransition = $state->getNextTransition();

        if (null !== $nextTransition) {
            foreach ($enabledTransitions as $transition) {
                if ($transition->getName() === $nextTransition) {
                    return $nextTransition;
                }
            }

            $availableNames = array_map(static fn (Transition $t): string => $t->getName(), $enabledTransitions);

            throw new TransitionResolutionException(\sprintf('Transition "%s" is not enabled from place "%s". Available transitions: "%s".', $nextTransition, $currentPlace, implode('", "', $availableNames)));
        }

        if (1 === \count($enabledTransitions)) {
            return $enabledTransitions[array_key_first($enabledTransitions)]->getName();
        }

        $availableNames = array_map(static fn (Transition $t): string => $t->getName(), $enabledTransitions);

        throw new TransitionResolutionException(\sprintf('Multiple transitions are enabled from place "%s" and no "_next_transition" hint was set in state. Available transitions: "%s".', $currentPlace, implode('", "', $availableNames)));
    }
}
