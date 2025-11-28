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

use Symfony\AI\PromptTemplate\Exception\InvalidArgumentException;

/**
 * Simple string replacement renderer.
 *
 * Replaces {variable} placeholders with values from the provided array.
 * Has zero external dependencies.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final readonly class StringRenderer implements RendererInterface
{
    public function render(string $template, array $values): string
    {
        $result = $template;

        foreach ($values as $key => $value) {
            if (!\is_string($key)) {
                throw InvalidArgumentException::invalidVariableName(get_debug_type($key));
            }

            if (!\is_string($value) && !is_numeric($value) && !$value instanceof \Stringable) {
                throw InvalidArgumentException::invalidVariableValue($key, get_debug_type($value));
            }

            $result = str_replace('{'.$key.'}', (string) $value, $result);
        }

        return $result;
    }
}
