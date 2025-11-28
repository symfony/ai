<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\PromptTemplate\Renderer;

use Symfony\AI\PromptTemplate\Exception\RenderingException;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * Renderer using Symfony Expression Language.
 *
 * Supports {expression} placeholders with full expression syntax including:
 * - Variable access: {user.name}
 * - Math operations: {price * quantity}
 * - Conditionals: {age > 18 ? "adult" : "minor"}
 * - String methods: {name.upper()}
 * - Array access: {items[0]}
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final readonly class ExpressionRenderer implements RendererInterface
{
    private ExpressionLanguage $expressionLanguage;

    public function __construct(?ExpressionLanguage $expressionLanguage = null)
    {
        $this->expressionLanguage = $expressionLanguage ?? new ExpressionLanguage();
    }

    public function render(string $template, array $values): string
    {
        $result = preg_replace_callback(
            '/{([^}]+)}/',
            function (array $matches) use ($values): string {
                try {
                    $evaluated = $this->expressionLanguage->evaluate(
                        $matches[1],
                        $values
                    );

                    return (string) $evaluated;
                } catch (\Throwable $e) {
                    throw RenderingException::expressionEvaluationFailed($matches[1], $e);
                }
            },
            $template
        );

        if (null === $result) {
            throw RenderingException::templateProcessingFailed();
        }

        return $result;
    }
}
