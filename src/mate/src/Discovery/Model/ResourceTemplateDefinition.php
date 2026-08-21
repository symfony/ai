<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Discovery\Model;

/**
 * A discovered Mate resource template, produced from a method carrying the
 * `#[AsResourceTemplate]` attribute.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ResourceTemplateDefinition
{
    /**
     * @param class-string $handlerClass
     */
    public function __construct(
        public readonly string $uriTemplate,
        public readonly ?string $name,
        public readonly ?string $title,
        public readonly ?string $description,
        public readonly ?string $mimeType,
        public readonly string $handlerClass,
        public readonly string $handlerMethod,
    ) {
    }

    public function handlerLabel(): string
    {
        return $this->handlerClass.'::'.$this->handlerMethod;
    }

    /**
     * Builds a regular expression that matches URIs against this template and
     * captures each `{variable}` as a named group.
     */
    public function toRegex(): string
    {
        // preg_quote escapes the placeholder braces to `\{name\}`; rewrite those into
        // named capture groups that stop at path separators.
        $pattern = preg_replace_callback(
            '/\\\\\{(\w+)\\\\\}/',
            static fn (array $matches): string => '(?P<'.$matches[1].'>[^/]+)',
            preg_quote($this->uriTemplate, '#'),
        );

        return '#^'.$pattern.'$#';
    }

    /**
     * Returns the placeholder variables extracted from a URI, or null when the URI
     * does not match this template.
     *
     * @return array<string, string>|null
     */
    public function extractVariables(string $uri): ?array
    {
        if (1 !== preg_match($this->toRegex(), $uri, $matches)) {
            return null;
        }

        $variables = [];
        foreach ($matches as $key => $value) {
            if (\is_string($key)) {
                $variables[$key] = $value;
            }
        }

        return $variables;
    }
}
