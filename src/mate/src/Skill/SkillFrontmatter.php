<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Skill;

/**
 * Reads and rewrites the leading YAML front matter of a SKILL.md file.
 *
 * SKILL.md front matter only needs the flat "name" and "description" scalars, so a tiny
 * line-based parser is used deliberately instead of pulling in a full YAML dependency.
 * Values may be wrapped in single or double quotes; those are stripped on read.
 *
 * Block scalars ("description: >-" and friends) are supported because descriptions are routinely
 * long enough to wrap — the built-in system-information skill uses one. Only the subset that makes
 * sense here is handled: the block is every following line indented deeper than the key, folded
 * with ">" and kept line-by-line with "|". Indentation is compared against column zero, which is
 * where front matter keys sit in practice.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SkillFrontmatter
{
    private const PATTERN = '/\A(?:\xEF\xBB\xBF)?---\r?\n(.*?)\r?\n---[ \t]*(?:\r?\n|\z)/s';

    /**
     * Parses the front matter block into a flat key => value map.
     *
     * @return array<string, string>|null null when no parseable front matter block is present
     */
    public function parse(string $content): ?array
    {
        if (1 !== preg_match(self::PATTERN, $content, $matches)) {
            return null;
        }

        $lines = explode("\n", $matches[1]);
        $result = [];

        for ($index = 0, $total = \count($lines); $index < $total; ++$index) {
            $line = rtrim($lines[$index], "\r");
            $trimmed = trim($line);
            if ('' === $trimmed || str_starts_with($trimmed, '#')) {
                continue;
            }

            $separator = strpos($line, ':');
            if (false === $separator) {
                continue;
            }

            $key = trim(substr($line, 0, $separator));
            if ('' === $key) {
                continue;
            }

            $value = trim(substr($line, $separator + 1));

            if (1 === preg_match('/^[>|][-+]?\d*$/', $value)) {
                $result[$key] = $this->readBlockScalar($lines, $index, str_starts_with($value, '>'));

                continue;
            }

            $result[$key] = $this->unquote($value);
        }

        return $result;
    }

    /**
     * Rewrites the front matter "name" value, leaving the rest of the document untouched.
     *
     * When no front matter or no "name" line is present, the content is returned unchanged.
     */
    public function rewriteName(string $content, string $newName): string
    {
        if (1 !== preg_match(self::PATTERN, $content, $matches, \PREG_OFFSET_CAPTURE)) {
            return $content;
        }

        $blockContent = $matches[1][0];
        $blockOffset = $matches[1][1];

        // A callback keeps $newName literal: it comes from a third-party directory name, and as a
        // replacement string a "$1" or a backslash in it would be read as a backreference/escape.
        $rewrittenBlock = preg_replace_callback(
            '/^([ \t]*name[ \t]*:[ \t]*).*$/m',
            static fn (array $match): string => $match[1].$newName,
            $blockContent,
            1,
            $count
        );

        if (null === $rewrittenBlock || 0 === $count) {
            return $content;
        }

        return substr($content, 0, $blockOffset).$rewrittenBlock.substr($content, $blockOffset + \strlen($blockContent));
    }

    /**
     * Consumes the indented lines belonging to a block scalar, advancing the cursor past them.
     *
     * @param list<string> $lines
     */
    private function readBlockScalar(array $lines, int &$index, bool $folded): string
    {
        $collected = [];
        $total = \count($lines);

        while ($index + 1 < $total) {
            $next = rtrim($lines[$index + 1], "\r");

            // A blank line stays inside the block and separates paragraphs.
            if ('' === trim($next)) {
                $collected[] = '';
                ++$index;

                continue;
            }

            // The block ends at the first line that is no longer indented.
            if (!str_starts_with($next, ' ') && !str_starts_with($next, "\t")) {
                break;
            }

            $collected[] = trim($next);
            ++$index;
        }

        while ([] !== $collected && '' === $collected[\count($collected) - 1]) {
            array_pop($collected);
        }

        if (!$folded) {
            return implode("\n", $collected);
        }

        $text = '';
        foreach ($collected as $line) {
            if ('' === $line) {
                $text = rtrim($text)."\n\n";

                continue;
            }

            $text .= $line.' ';
        }

        return trim($text);
    }

    private function unquote(string $value): string
    {
        if (\strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[\strlen($value) - 1];
            if (('"' === $first && '"' === $last) || ("'" === $first && "'" === $last)) {
                return substr($value, 1, -1);
            }
        }

        return $value;
    }
}
