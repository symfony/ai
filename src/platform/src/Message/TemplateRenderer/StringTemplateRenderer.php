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

/**
 * Simple string replacement renderer.
 *
 * Replaces {variable} placeholders with values from the provided array.
 * Has zero external dependencies.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class StringTemplateRenderer implements TemplateRendererInterface
{
    public function supports(string $type): bool
    {
        return 'string' === $type;
    }

    public function render(Template $template, array $variables): string
    {
        $result = $template->getTemplate();

        foreach ($this->flattenVariables($variables) as $key => $value) {
            if (null === $value) {
                $value = '';
            }

            if (!\is_string($key)) {
                throw new InvalidArgumentException(\sprintf('Template variable keys must be strings, "%s" given.', get_debug_type($key)));
            }

            if (!\is_string($value) && !is_numeric($value) && !$value instanceof \Stringable) {
                throw new InvalidArgumentException(\sprintf('Template variable "%s" must be string, numeric or Stringable, "%s" given.', $key, get_debug_type($value)));
            }

            $result = str_replace('{'.$key.'}', (string) $value, $result);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $variables
     *
     * @return array<string, mixed>
     */
    private function flattenVariables(array $variables, string $prefix = ''): array
    {
        $flattened = [];

        foreach ($variables as $key => $value) {
            $fullKey = $prefix ? $prefix.'.'.$key : $key;

            if (\is_array($value)) {
                $flattened = array_merge($flattened, $this->flattenVariables($value, $fullKey));
            } else {
                $flattened[$fullKey] = $value;
            }
        }

        return $flattened;
    }
}
