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

use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Exception\TransitionResolutionException;
use Symfony\AI\Agent\Workflow\TransitionResolverInterface;
use Symfony\AI\Agent\Workflow\WorkflowStateInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Resolves the next transition by evaluating one boolean expression per candidate transition.
 *
 * The first expression that evaluates truthy and whose transition is currently enabled wins. When no
 * expression matches, resolution falls back to {@see StateBasedTransitionResolver} (single enabled
 * transition picked automatically, ambiguity throws).
 *
 * The following variables are available to every expression:
 *
 *  * ``state``       — the {@see WorkflowStateInterface};
 *  * ``data``        — the state data array (``state.all()``);
 *  * ``place``       — the current place name;
 *  * ``transitions`` — the list of currently enabled transition names.
 *
 * The expressions are trusted developer-authored input, like symfony/workflow's own guards: they are
 * evaluated with an unsandboxed ExpressionLanguage and can call methods on the exposed ``state``
 * object, so they must never be built from end-user input.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ExpressionTransitionResolver implements TransitionResolverInterface
{
    private readonly ExpressionLanguage $expressionLanguage;
    private readonly StateBasedTransitionResolver $fallbackResolver;

    /**
     * @param array<non-empty-string, string> $expressions Map of transition name to a boolean expression
     */
    public function __construct(
        private readonly array $expressions,
        ?ExpressionLanguage $expressionLanguage = null,
    ) {
        if (!class_exists(ExpressionLanguage::class)) {
            throw new InvalidArgumentException('The "symfony/expression-language" package is required to use the ExpressionTransitionResolver.');
        }

        $this->expressionLanguage = $expressionLanguage ?? new ExpressionLanguage();
        $this->fallbackResolver = new StateBasedTransitionResolver();
    }

    public function resolve(WorkflowStateInterface $state, string $currentPlace, WorkflowInterface $workflow, object $subject): ?string
    {
        $enabledTransitions = $workflow->getEnabledTransitions($subject);

        if ([] === $enabledTransitions) {
            return null;
        }

        $enabledNames = array_map(static fn (Transition $t): string => $t->getName(), $enabledTransitions);

        $variables = [
            'state' => $state,
            'data' => $state->all(),
            'place' => $currentPlace,
            'transitions' => $enabledNames,
        ];

        foreach ($this->expressions as $transitionName => $expression) {
            if (!\in_array((string) $transitionName, $enabledNames, true)) {
                continue;
            }

            try {
                $matches = $this->expressionLanguage->evaluate($expression, $variables);
            } catch (\Throwable $exception) {
                throw new TransitionResolutionException(\sprintf('Failed to evaluate the transition expression for "%s" at place "%s": "%s".', $transitionName, $currentPlace, $exception->getMessage()), 0, $exception);
            }

            if ($matches) {
                return (string) $transitionName;
            }
        }

        return $this->fallbackResolver->resolve($state, $currentPlace, $workflow, $subject);
    }
}
