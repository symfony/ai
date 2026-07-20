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
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * Guard that allows a place to execute when a boolean expression evaluates truthy.
 *
 * The following variables are available to the expression:
 *
 *  * ``state`` — the {@see WorkflowStateInterface};
 *  * ``data``  — the state data array (``state.all()``);
 *  * ``place`` — the place being entered.
 *
 * The expression is trusted developer-authored input, like symfony/workflow's own guards: it is
 * evaluated with an unsandboxed ExpressionLanguage and can call methods on the exposed ``state``
 * object, so it must never be built from end-user input.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ExpressionGuard extends AbstractGuard
{
    private readonly ExpressionLanguage $expressionLanguage;

    /**
     * @param non-empty-string       $expression Boolean expression; the place may execute when it is truthy
     * @param list<non-empty-string> $places     Places this guard applies to; an empty list applies to every place
     */
    public function __construct(
        private readonly string $expression,
        array $places = [],
        ?ExpressionLanguage $expressionLanguage = null,
    ) {
        parent::__construct($places);

        if (!class_exists(ExpressionLanguage::class)) {
            throw new InvalidArgumentException('The "symfony/expression-language" package is required to use the ExpressionGuard.');
        }

        $this->expressionLanguage = $expressionLanguage ?? new ExpressionLanguage();
    }

    public function allows(WorkflowStateInterface $state, string $place): bool
    {
        try {
            return (bool) $this->expressionLanguage->evaluate($this->expression, [
                'state' => $state,
                'data' => $state->all(),
                'place' => $place,
            ]);
        } catch (\Throwable $exception) {
            throw new RuntimeException(\sprintf('Failed to evaluate the guard expression at place "%s": "%s".', $place, $exception->getMessage()), previous: $exception);
        }
    }
}
