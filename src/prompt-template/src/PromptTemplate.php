<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\PromptTemplate;

use Symfony\AI\PromptTemplate\Renderer\RendererInterface;
use Symfony\AI\PromptTemplate\Renderer\StringRenderer;

/**
 * Prompt template with extensible rendering strategy.
 *
 * Supports variable substitution using a pluggable renderer system.
 * Defaults to simple {variable} replacement via StringRenderer.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final readonly class PromptTemplate implements PromptTemplateInterface
{
    private RendererInterface $renderer;

    public function __construct(
        private string $template,
        ?RendererInterface $renderer = null,
    ) {
        $this->renderer = $renderer ?? new StringRenderer();
    }

    public function __toString(): string
    {
        return $this->template;
    }

    public function format(array $values = []): string
    {
        return $this->renderer->render($this->template, $values);
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public static function fromString(string $template): self
    {
        return new self($template);
    }

    public static function fromStringWithRenderer(string $template, RendererInterface $renderer): self
    {
        return new self($template, $renderer);
    }
}
