<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Message\TemplateRenderer;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Template;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ExpressionLanguageTemplateRenderer implements TemplateRendererInterface
{
    private ExpressionLanguage $expressionLanguage;

    public function __construct(?ExpressionLanguage $expressionLanguage = null)
    {
        if (!class_exists(ExpressionLanguage::class)) {
            throw new InvalidArgumentException('ExpressionTemplateRenderer requires "symfony/expression-language" package.');
        }

        $this->expressionLanguage = $expressionLanguage ?? new ExpressionLanguage();
    }

    public function supports(string $type): bool
    {
        return 'expression' === $type;
    }

    public function render(Template $template, array $variables): string
    {
        try {
            return (string) $this->expressionLanguage->evaluate(
                $template->getTemplate(),
                $variables
            );
        } catch (\Throwable $e) {
            throw new InvalidArgumentException(\sprintf('Failed to render expression template: "%s"', $e->getMessage()), previous: $e);
        }
    }
}
