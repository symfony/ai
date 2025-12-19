<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Message;

use Symfony\AI\Platform\Message\Content\ContentInterface;

/**
 * Message template with type-based rendering strategy.
 *
 * Supports variable substitution using different rendering types.
 * Rendering happens externally during message serialization when template_vars are provided.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class Template implements \Stringable, ContentInterface
{
    public function __construct(
        private readonly string $template,
        private readonly string $type,
    ) {
    }

    public function __toString(): string
    {
        return $this->template;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public static function string(string $template): self
    {
        return new self($template, 'string');
    }

    public static function expression(string $template): self
    {
        return new self($template, 'expression');
    }
}
