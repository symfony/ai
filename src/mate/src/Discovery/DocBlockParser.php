<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Discovery;

/**
 * Lightweight PHPDoc parser for method doc comments.
 *
 * Extracts the leading summary and `@param` type/description information without pulling
 * in a full PHPDoc library. Types are read as written (e.g. `int`, `string|null`,
 * `string[]`, `array<string>`); complex multi-line descriptions are not supported by
 * design, matching how Mate tool signatures are documented.
 *
 * @phpstan-type ParamTag array{type: string|null, description: string|null}
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class DocBlockParser
{
    /**
     * Returns the summary text (the first block before any tag or blank line).
     */
    public function getSummary(string|false|null $docComment): ?string
    {
        $lines = $this->normalize($docComment);
        if ([] === $lines) {
            return null;
        }

        $summaryLines = [];
        foreach ($lines as $line) {
            if (str_starts_with($line, '@')) {
                break;
            }

            if ('' === $line) {
                if ([] !== $summaryLines) {
                    break;
                }

                continue;
            }

            $summaryLines[] = $line;
        }

        if ([] === $summaryLines) {
            return null;
        }

        return implode(' ', $summaryLines);
    }

    /**
     * Returns the `@param` tags keyed by parameter name (without the leading `$`).
     *
     * @return array<string, ParamTag>
     */
    public function getParamTags(string|false|null $docComment): array
    {
        $tags = [];
        foreach ($this->normalize($docComment) as $line) {
            if (!str_starts_with($line, '@param')) {
                continue;
            }

            $remainder = trim(substr($line, \strlen('@param')));
            if (1 !== preg_match('/^(?:(?<type>[^$\s]\S*)\s+)?\$(?<name>\w+)\b\s*(?<desc>.*)$/', $remainder, $matches)) {
                continue;
            }

            $type = '' !== $matches['type'] ? ltrim($matches['type'], '\\') : null;
            $description = '' !== trim($matches['desc']) ? trim($matches['desc']) : null;

            $tags[$matches['name']] = [
                'type' => $type,
                'description' => $description,
            ];
        }

        return $tags;
    }

    /**
     * Splits a raw doc comment into trimmed content lines (without the comment markers).
     *
     * @return list<string>
     */
    private function normalize(string|false|null $docComment): array
    {
        if (false === $docComment || null === $docComment || '' === $docComment) {
            return [];
        }

        $withoutOpen = preg_replace('#^\s*/\*\*#', '', $docComment);
        $withoutClose = preg_replace('#\*/\s*$#', '', (string) $withoutOpen);

        $lines = [];
        foreach (preg_split('/\R/', (string) $withoutClose) ?: [] as $rawLine) {
            $line = preg_replace('/^\s*\*\s?/', '', $rawLine);
            $lines[] = rtrim((string) $line);
        }

        return $lines;
    }
}
