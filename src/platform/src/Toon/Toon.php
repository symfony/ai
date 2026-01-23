<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Toon;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;

/**
 * Token-Oriented Object Notation (TOON) encoder/decoder.
 *
 * @see https://github.com/toon-format/toon
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Toon
{
    public const DEFAULT_INDENT_SIZE = 2;
    public const DEFAULT_DELIMITER = ',';
    private const SPECIAL_CHARS = [':', '"', '\\', '[', ']', '{', '}'];
    private const ESCAPE_MAP = ['\\' => '\\\\', '"' => '\\"', "\n" => '\\n', "\r" => '\\r', "\t" => '\\t'];
    private const UNESCAPE_MAP = ['\\' => '\\', '"' => '"', 'n' => "\n", 'r' => "\r", 't' => "\t"];

    public function encode(mixed $value, int $depth = 0, int $indentSize = self::DEFAULT_INDENT_SIZE, string $delimiter = self::DEFAULT_DELIMITER): string
    {
        return match (true) {
            null === $value => 'null',
            \is_bool($value) => $value ? 'true' : 'false',
            \is_int($value), \is_float($value) => $this->encodeNumber($value),
            \is_string($value) => $this->encodeString($value, $delimiter),
            \is_array($value) => $this->encodeArray($value, $depth, $indentSize, $delimiter),
            \is_object($value) => $this->encodeArray((array) $value, $depth, $indentSize, $delimiter),
            default => throw new InvalidArgumentException(\sprintf('Cannot encode value of type "%s".', get_debug_type($value))),
        };
    }

    public function decode(string $data, int $indentSize = self::DEFAULT_INDENT_SIZE, bool $strict = true): mixed
    {
        $lines = explode("\n", $data);
        if ('' === end($lines)) {
            array_pop($lines);
        }

        if (0 === \count($lines)) {
            return [];
        }

        if (1 === \count($lines) && !$this->isStructuredLine($lines[0])) {
            return $this->decodeValue(trim($lines[0]));
        }

        return $this->doDecode($lines, 0, \count($lines), 0, $indentSize, $strict);
    }

    private function encodeNumber(int|float $value): string
    {
        if (\is_float($value)) {
            if (is_nan($value) || is_infinite($value)) {
                return 'null';
            }
            if (0.0 === $value && '-0' === (string) $value) {
                return '0';
            }
        }

        $result = (string) $value;
        if (\is_float($value) && str_contains($result, 'E')) {
            $result = rtrim(rtrim(number_format($value, 15, '.', ''), '0'), '.');
        }

        return $result;
    }

    private function encodeString(string $value, string $delimiter): string
    {
        if ($this->needsQuoting($value, $delimiter)) {
            return '"'.strtr($value, self::ESCAPE_MAP).'"';
        }

        return $value;
    }

    private function needsQuoting(string $value, string $delimiter): bool
    {
        if ('' === $value || $value !== trim($value) || is_numeric($value)) {
            return true;
        }

        if (\in_array(strtolower($value), ['true', 'false', 'null'], true)) {
            return true;
        }

        if (str_starts_with($value, '-')) {
            return true;
        }

        foreach (self::SPECIAL_CHARS as $char) {
            if (str_contains($value, $char)) {
                return true;
            }
        }

        if (str_contains($value, $delimiter) || preg_match('/[\x00-\x1F]/', $value)) {
            return true;
        }

        return false;
    }

    private function unescapeString(string $value): string
    {
        $result = '';
        $length = \strlen($value);

        for ($i = 0; $i < $length; ++$i) {
            if ('\\' === $value[$i] && $i + 1 < $length) {
                $next = $value[$i + 1];
                if (!isset(self::UNESCAPE_MAP[$next])) {
                    throw new RuntimeException(\sprintf('Invalid escape sequence "\\%s".', $next));
                }
                $result .= self::UNESCAPE_MAP[$next];
                ++$i;
            } else {
                $result .= $value[$i];
            }
        }

        return $result;
    }

    /**
     * @param array<mixed> $array
     */
    private function encodeArray(array $array, int $depth, int $indentSize, string $delimiter): string
    {
        if (0 === \count($array)) {
            return 0 === $depth ? '' : '[]';
        }

        if (array_is_list($array)) {
            return $this->encodeList($array, $depth, $indentSize, $delimiter);
        }

        return $this->encodeObject($array, $depth, $indentSize, $delimiter);
    }

    /**
     * @param array<int, mixed> $list
     */
    private function encodeList(array $list, int $depth, int $indentSize, string $delimiter): string
    {
        if ($this->isAllPrimitives($list)) {
            $values = array_map(fn ($v) => $this->encodePrimitive($v, $delimiter), $list);

            return \sprintf('[%d]: %s', \count($list), implode($delimiter, $values));
        }

        $fields = $this->getTabularFields($list);
        if (null !== $fields) {
            return $this->encodeTabular($list, $fields, $depth, $indentSize, $delimiter);
        }

        return $this->encodeMixedList($list, $depth, $indentSize, $delimiter);
    }

    /**
     * @param array<int, mixed> $list
     */
    private function isAllPrimitives(array $list): bool
    {
        foreach ($list as $item) {
            if (\is_array($item) || \is_object($item)) {
                return false;
            }
        }

        return true;
    }

    private function encodePrimitive(mixed $value, string $delimiter): string
    {
        return match (true) {
            null === $value => 'null',
            \is_bool($value) => $value ? 'true' : 'false',
            \is_int($value), \is_float($value) => $this->encodeNumber($value),
            \is_string($value) => $this->encodeString($value, $delimiter),
            default => throw new InvalidArgumentException(\sprintf('Cannot encode primitive of type "%s".', get_debug_type($value))),
        };
    }

    /**
     * @param array<int, mixed> $list
     *
     * @return array<string>|null
     */
    private function getTabularFields(array $list): ?array
    {
        $firstItem = $list[0] ?? null;
        if (!\is_array($firstItem) || array_is_list($firstItem)) {
            return null;
        }

        foreach ($firstItem as $v) {
            if (\is_array($v) || \is_object($v)) {
                return null;
            }
        }

        $fields = array_keys($firstItem);
        foreach ($list as $item) {
            if (!\is_array($item) || array_is_list($item) || array_keys($item) !== $fields) {
                return null;
            }
            foreach ($item as $v) {
                if (\is_array($v) || \is_object($v)) {
                    return null;
                }
            }
        }

        return $fields;
    }

    /**
     * @param array<int, array<string, mixed>> $list
     * @param array<string>                    $fields
     */
    private function encodeTabular(array $list, array $fields, int $depth, int $indentSize, string $delimiter): string
    {
        $indent = str_repeat(' ', $depth * $indentSize);
        $rowIndent = str_repeat(' ', ($depth + 1) * $indentSize);

        $lines = [\sprintf('%s[%d]{%s}:', $indent, \count($list), implode($delimiter, $fields))];
        foreach ($list as $item) {
            $values = array_map(fn ($f) => $this->encodePrimitive($item[$f], $delimiter), $fields);
            $lines[] = $rowIndent.implode($delimiter, $values);
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<int, mixed> $list
     */
    private function encodeMixedList(array $list, int $depth, int $indentSize, string $delimiter): string
    {
        $indent = str_repeat(' ', $depth * $indentSize);
        $itemIndent = str_repeat(' ', ($depth + 1) * $indentSize);

        $lines = [\sprintf('%s[%d]:', $indent, \count($list))];
        foreach ($list as $item) {
            if (\is_array($item) && !array_is_list($item)) {
                $objLines = $this->encodeObjectAsLines($item, $indentSize, $delimiter);
                $lines[] = $itemIndent.'- '.$objLines[0];
                $extraIndent = str_repeat(' ', ($depth + 2) * $indentSize);
                for ($i = 1; $i < \count($objLines); ++$i) {
                    $lines[] = $extraIndent.$objLines[$i];
                }
            } elseif (\is_array($item)) {
                $lines[] = $itemIndent.'- '.$this->encodeList($item, 0, $indentSize, $delimiter);
            } else {
                $lines[] = $itemIndent.'- '.$this->encodePrimitive($item, $delimiter);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $object
     *
     * @return array<string>
     */
    private function encodeObjectAsLines(array $object, int $indentSize, string $delimiter): array
    {
        $lines = [];
        foreach ($object as $key => $value) {
            $encodedKey = $this->encodeKey((string) $key);

            if (\is_array($value)) {
                if (0 === \count($value)) {
                    $lines[] = $encodedKey.': []';
                } elseif (array_is_list($value) && $this->isAllPrimitives($value)) {
                    $values = array_map(fn ($v) => $this->encodePrimitive($v, $delimiter), $value);
                    $lines[] = \sprintf('%s[%d]: %s', $encodedKey, \count($value), implode($delimiter, $values));
                } elseif (!array_is_list($value)) {
                    $nestedLines = $this->encodeObjectAsLines($value, $indentSize, $delimiter);
                    $lines[] = $encodedKey.':';
                    foreach ($nestedLines as $nestedLine) {
                        $lines[] = str_repeat(' ', $indentSize).$nestedLine;
                    }
                } else {
                    $arrayLines = explode("\n", $this->encodeList($value, 0, $indentSize, $delimiter));
                    $lines[] = $encodedKey.$arrayLines[0];
                    for ($i = 1; $i < \count($arrayLines); ++$i) {
                        $lines[] = str_repeat(' ', $indentSize).$arrayLines[$i];
                    }
                }
            } else {
                $lines[] = $encodedKey.': '.$this->encodePrimitive($value, $delimiter);
            }
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $object
     */
    private function encodeObject(array $object, int $depth, int $indentSize, string $delimiter): string
    {
        $indent = str_repeat(' ', $depth * $indentSize);
        $childIndent = str_repeat(' ', ($depth + 1) * $indentSize);
        $lines = [];

        foreach ($object as $key => $value) {
            $encodedKey = $this->encodeKey((string) $key);

            if (!\is_array($value)) {
                $lines[] = $indent.$encodedKey.': '.$this->encodePrimitive($value, $delimiter);
                continue;
            }

            if (0 === \count($value)) {
                $lines[] = $indent.$encodedKey.': []';
                continue;
            }

            if (!array_is_list($value)) {
                $lines[] = $indent.$encodedKey.':';
                $lines[] = $this->encodeObject($value, $depth + 1, $indentSize, $delimiter);
                continue;
            }

            if ($this->isAllPrimitives($value)) {
                $values = array_map(fn ($v) => $this->encodePrimitive($v, $delimiter), $value);
                $lines[] = $indent.\sprintf('%s[%d]: %s', $encodedKey, \count($value), implode($delimiter, $values));
                continue;
            }

            $fields = $this->getTabularFields($value);
            if (null !== $fields) {
                $lines[] = $indent.\sprintf('%s[%d]{%s}:', $encodedKey, \count($value), implode($delimiter, $fields));
                foreach ($value as $item) {
                    $rowValues = array_map(fn ($f) => $this->encodePrimitive($item[$f], $delimiter), $fields);
                    $lines[] = $childIndent.implode($delimiter, $rowValues);
                }
                continue;
            }

            $lines[] = $indent.\sprintf('%s[%d]:', $encodedKey, \count($value));
            foreach ($value as $item) {
                if (\is_array($item) && !array_is_list($item)) {
                    $objLines = $this->encodeObjectAsLines($item, $indentSize, $delimiter);
                    $lines[] = $childIndent.'- '.$objLines[0];
                    $extraIndent = str_repeat(' ', ($depth + 2) * $indentSize);
                    for ($i = 1; $i < \count($objLines); ++$i) {
                        $lines[] = $extraIndent.$objLines[$i];
                    }
                } elseif (\is_array($item)) {
                    $lines[] = $childIndent.'- '.$this->encodeList($item, 0, $indentSize, $delimiter);
                } else {
                    $lines[] = $childIndent.'- '.$this->encodePrimitive($item, $delimiter);
                }
            }
        }

        return implode("\n", $lines);
    }

    private function encodeKey(string $key): string
    {
        if (preg_match('/[:\"\\\\\[\]{}]|[\x00-\x1F]/', $key)) {
            return '"'.strtr($key, self::ESCAPE_MAP).'"';
        }

        return $key;
    }

    private function isStructuredLine(string $line): bool
    {
        $trimmed = trim($line);
        if ('' === $trimmed || preg_match('/^"(?:[^"\\\\]|\\\\.)*"$/', $trimmed)) {
            return false;
        }

        return (bool) preg_match('/^("(?:[^"\\\\]|\\\\.)*"|[^:\[{]+)(\[\d+\])?(\{[^}]*\})?\s*:/', $trimmed);
    }

    /**
     * @param array<string> $lines
     *
     * @return array<string, mixed>
     */
    private function doDecode(array $lines, int $start, int $end, int $baseIndent, int $indentSize, bool $strict): array
    {
        $result = [];
        $i = $start;

        while ($i < $end) {
            $line = $lines[$i];
            if ('' === trim($line)) {
                ++$i;
                continue;
            }

            $indent = \strlen($line) - \strlen(ltrim($line, ' '));
            if ($indent < $baseIndent) {
                break;
            }
            if ($indent > $baseIndent) {
                ++$i;
                continue;
            }

            $trimmed = trim($line);
            if (str_starts_with($trimmed, '- ')) {
                $result[] = $this->decodeListItem(substr($trimmed, 2), $lines, $i, $end, $indent, $indentSize, $strict);
                $i = $this->findNextLine($lines, $i + 1, $end, $indent);
                continue;
            }

            $parsed = $this->parseKeyLine($trimmed);
            if (null === $parsed) {
                ++$i;
                continue;
            }

            [$key, $count, $fields, $value, $delim] = $parsed;

            if (null !== $count) {
                if (null !== $fields) {
                    $result[$key] = $this->decodeTabular($lines, $i + 1, $indent + $indentSize, $count, $fields, $strict, $delim);
                    $i = $this->skipRows($lines, $i + 1, $end, $indent + $indentSize, $count);
                } elseif ('' !== $value) {
                    $result[$key] = $this->decodeInlineArray($value, $count, $strict, $delim);
                    ++$i;
                } else {
                    $result[$key] = $this->decodeMixedArray($lines, $i + 1, $end, $indent + $indentSize, $indentSize, $count, $strict, $delim);
                    $i = $this->findNextLine($lines, $i + 1, $end, $indent);
                }
            } elseif ('' !== $value) {
                $result[$key] = $this->decodeValue($value);
                ++$i;
            } else {
                $blockEnd = $this->findBlockEnd($lines, $i + 1, $end, $indent + $indentSize);
                $result[$key] = $this->doDecode($lines, $i + 1, $blockEnd, $indent + $indentSize, $indentSize, $strict);
                $i = $blockEnd;
            }
        }

        return $result;
    }

    /**
     * @return array{string, int|null, array<string>|null, string, string}|null
     */
    private function parseKeyLine(string $line): ?array
    {
        if (!preg_match('/^("(?:[^"\\\\]|\\\\.)*"|[^\[:{]+?)(\[(\d+)([,|\t])?\])?(\{([^}]*)\})?\s*:\s*(.*)$/', $line, $m)) {
            return null;
        }

        $key = $this->decodeKey($m[1]);
        $count = '' !== $m[3] ? (int) $m[3] : null;
        $delim = '' !== $m[4] ? $m[4] : self::DEFAULT_DELIMITER;
        $fields = '' !== $m[6] ? array_map('trim', explode($delim, $m[6])) : null;

        return [$key, $count, $fields, trim($m[7]), $delim];
    }

    private function decodeKey(string $key): string
    {
        $key = trim($key);
        if (str_starts_with($key, '"') && str_ends_with($key, '"')) {
            return $this->unescapeString(substr($key, 1, -1));
        }

        return $key;
    }

    private function decodeValue(string $value): mixed
    {
        $value = trim($value);

        if ('' === $value) {
            return '';
        }
        if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
            return $this->unescapeString(substr($value, 1, -1));
        }
        if ('null' === $value) {
            return null;
        }
        if ('true' === $value) {
            return true;
        }
        if ('false' === $value) {
            return false;
        }
        if ('[]' === $value) {
            return [];
        }
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return $value;
    }

    /**
     * @param array<string> $lines
     * @param array<string> $fields
     *
     * @return array<int, array<string, mixed>>
     */
    private function decodeTabular(array $lines, int $start, int $expectedIndent, int $count, array $fields, bool $strict, string $delimiter): array
    {
        $result = [];

        for ($i = $start; $i < \count($lines) && \count($result) < $count; ++$i) {
            $line = $lines[$i];
            if ('' === trim($line)) {
                continue;
            }

            $indent = \strlen($line) - \strlen(ltrim($line, ' '));
            if ($indent !== $expectedIndent) {
                break;
            }

            $values = $this->splitValues(trim($line), $delimiter);
            if ($strict && \count($values) !== \count($fields)) {
                throw new RuntimeException(\sprintf('Tabular row width mismatch: expected %d values, got %d.', \count($fields), \count($values)));
            }

            $row = [];
            foreach ($fields as $idx => $field) {
                $row[$field] = $this->decodeValue($values[$idx] ?? '');
            }
            $result[] = $row;
        }

        if ($strict && \count($result) !== $count) {
            throw new RuntimeException(\sprintf('Array count mismatch: declared %d, got %d.', $count, \count($result)));
        }

        return $result;
    }

    /**
     * @return array<string>
     */
    private function splitValues(string $line, string $delimiter): array
    {
        $values = [];
        $current = '';
        $inQuotes = false;

        for ($i = 0; $i < \strlen($line); ++$i) {
            $char = $line[$i];
            if ('"' === $char && !$inQuotes) {
                $inQuotes = true;
                $current .= $char;
            } elseif ('"' === $char && $inQuotes && (0 === $i || '\\' !== $line[$i - 1])) {
                $inQuotes = false;
                $current .= $char;
            } elseif ($char === $delimiter && !$inQuotes) {
                $values[] = trim($current);
                $current = '';
            } else {
                $current .= $char;
            }
        }
        $values[] = trim($current);

        return $values;
    }

    /**
     * @return array<mixed>
     */
    private function decodeInlineArray(string $value, int $count, bool $strict, string $delimiter): array
    {
        $values = $this->splitValues($value, $delimiter);

        if ($strict && \count($values) !== $count) {
            throw new RuntimeException(\sprintf('Array count mismatch: declared %d, got %d.', $count, \count($values)));
        }

        return array_map(fn ($v) => $this->decodeValue($v), $values);
    }

    /**
     * @param array<string> $lines
     *
     * @return array<mixed>
     */
    private function decodeMixedArray(array $lines, int $start, int $end, int $baseIndent, int $indentSize, int $count, bool $strict, string $delimiter): array
    {
        $result = [];
        $i = $start;

        while ($i < $end && \count($result) < $count) {
            if ($i >= \count($lines)) {
                break;
            }

            $line = $lines[$i];
            if ('' === trim($line)) {
                ++$i;
                continue;
            }

            $indent = \strlen($line) - \strlen(ltrim($line, ' '));
            if ($indent < $baseIndent) {
                break;
            }
            if ($indent !== $baseIndent) {
                ++$i;
                continue;
            }

            $trimmed = trim($line);
            if (!str_starts_with($trimmed, '- ')) {
                ++$i;
                continue;
            }

            $result[] = $this->decodeListItem(substr($trimmed, 2), $lines, $i, $end, $indent, $indentSize, $strict);
            $i = $this->findNextLine($lines, $i + 1, $end, $baseIndent);
        }

        if ($strict && \count($result) !== $count) {
            throw new RuntimeException(\sprintf('Array count mismatch: declared %d, got %d.', $count, \count($result)));
        }

        return $result;
    }

    /**
     * @param array<string> $lines
     */
    private function decodeListItem(string $content, array $lines, int $lineIndex, int $end, int $baseIndent, int $indentSize, bool $strict): mixed
    {
        if (preg_match('/^\[(\d+)\]:\s*(.+)$/', $content, $m)) {
            return $this->decodeInlineArray($m[2], (int) $m[1], $strict, self::DEFAULT_DELIMITER);
        }

        if ($this->isStructuredLine($content)) {
            $parsed = $this->parseKeyLine($content);
            if (null !== $parsed) {
                $obj = [$parsed[0] => '' !== $parsed[3] ? $this->decodeValue($parsed[3]) : []];

                $childIndent = $baseIndent + $indentSize;
                for ($i = $lineIndex + 1; $i < $end; ++$i) {
                    $childLine = $lines[$i];
                    if ('' === trim($childLine)) {
                        continue;
                    }
                    $actualIndent = \strlen($childLine) - \strlen(ltrim($childLine, ' '));
                    if ($actualIndent < $childIndent) {
                        break;
                    }
                    if ($actualIndent === $childIndent) {
                        $childParsed = $this->parseKeyLine(trim($childLine));
                        if (null !== $childParsed) {
                            $obj[$childParsed[0]] = '' !== $childParsed[3] ? $this->decodeValue($childParsed[3]) : [];
                        }
                    }
                }

                return $obj;
            }
        }

        return $this->decodeValue($content);
    }

    /**
     * @param array<string> $lines
     */
    private function findBlockEnd(array $lines, int $start, int $end, int $minIndent): int
    {
        for ($i = $start; $i < $end; ++$i) {
            if ('' === trim($lines[$i])) {
                continue;
            }
            if (\strlen($lines[$i]) - \strlen(ltrim($lines[$i], ' ')) < $minIndent) {
                return $i;
            }
        }

        return $end;
    }

    /**
     * @param array<string> $lines
     */
    private function findNextLine(array $lines, int $start, int $end, int $targetIndent): int
    {
        for ($i = $start; $i < $end; ++$i) {
            if ('' === trim($lines[$i])) {
                continue;
            }
            if (\strlen($lines[$i]) - \strlen(ltrim($lines[$i], ' ')) <= $targetIndent) {
                return $i;
            }
        }

        return $end;
    }

    /**
     * @param array<string> $lines
     */
    private function skipRows(array $lines, int $start, int $end, int $expectedIndent, int $count): int
    {
        $rowCount = 0;
        for ($i = $start; $i < $end && $rowCount < $count; ++$i) {
            if ('' === trim($lines[$i])) {
                continue;
            }
            if (\strlen($lines[$i]) - \strlen(ltrim($lines[$i], ' ')) !== $expectedIndent) {
                break;
            }
            ++$rowCount;
        }

        return $i;
    }
}
