<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Evaluator\Scorer;

use Symfony\AI\Evaluator\ScorerInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\ToolCall;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ToolCallScorer implements ScorerInterface
{
    /**
     * @param list<string> $toolCalls The names of the tools expected to be called
     */
    public function __construct(
        private readonly array $toolCalls,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function score(DeferredResult $deferredResult, array $options = []): float
    {
        if ([] === $this->toolCalls) {
            return 0.0;
        }

        $calledTools = array_map(
            static fn (ToolCall $toolCall): string => $toolCall->getName(),
            $deferredResult->asToolCalls(),
        );

        if ([] === $calledTools) {
            return 0.0;
        }

        return \count(array_intersect($this->toolCalls, $calledTools)) / \count($this->toolCalls);
    }
}
