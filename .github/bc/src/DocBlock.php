<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Bc;

/**
 * Reads the tags that shape the public API from a PHPDoc comment.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class DocBlock
{
    /**
     * @param array<string, string>      $methods    @method signatures, keyed by lowercase name
     * @param array<string, string|null> $parameters @param types, keyed by parameter name
     */
    private function __construct(
        public readonly bool $internal,
        public readonly bool $announcedInternal,
        public readonly bool $experimental,
        public readonly ?string $deprecated,
        public readonly ?string $final,
        public readonly array $methods,
        public readonly array $parameters,
    ) {
    }

    public static function parse(?string $comment): self
    {
        if (null === $comment || '' === $comment) {
            return new self(false, false, false, null, null, [], []);
        }

        $lines = [];
        foreach (explode("\n", $comment) as $line) {
            $lines[] = trim(preg_replace(['#^\s*(/\*\*|\*/|\*)?#', '#\*/\s*$#'], '', $line) ?? '');
        }
        $text = implode("\n", $lines);

        $methods = [];
        preg_match_all('/^@method\s+(?:static\s+)?(?:(.+?)\s+)?(\w+)\s*\(([^)]*)\)/m', $text, $matches, \PREG_SET_ORDER);
        foreach ($matches as $match) {
            $returnType = '' !== $match[1] ? ': '.$match[1] : '';
            $methods[strtolower($match[2])] = \sprintf('%s(%s)%s', $match[2], preg_replace('/\s+/', ' ', trim($match[3])), $returnType);
        }

        $parameters = [];
        preg_match_all('/^@param\s+(?:([^$\s][^$]*?)\s+)?&?\s*(?:\.\.\.)?\$(\w+)/m', $text, $matches, \PREG_SET_ORDER);
        foreach ($matches as $match) {
            $parameters[$match[2]] = '' !== $match[1] ? $match[1] : null;
        }

        $internal = self::tag($text, 'internal');

        return new self(
            null !== $internal && !str_starts_with($internal, 'since'),
            null !== $internal && str_starts_with($internal, 'since'),
            1 === preg_match('/^@experimental\b/m', $text),
            self::tag($text, 'deprecated'),
            self::tag($text, 'final'),
            $methods,
            $parameters,
        );
    }

    /**
     * @return string|null the tag description, "" when the tag has none, null when the tag is absent
     */
    private static function tag(string $text, string $name): ?string
    {
        if (1 !== preg_match('/^@'.$name.'\b(.*)$/m', $text, $match)) {
            return null;
        }

        return trim($match[1]);
    }
}
