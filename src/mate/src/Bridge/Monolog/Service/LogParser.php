<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Monolog\Service;

use Symfony\AI\Mate\Bridge\Monolog\Model\LogEntry;

/**
 * Parses log lines from both JSON and standard Monolog line formats.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class LogParser
{
    /**
     * Standard Monolog line format pattern.
     * Matches: [2024-01-15 10:30:45] channel.LEVEL: Message {"context"} {"extra"}.
     * Note: Context and extra JSON objects are parsed separately since regex cannot handle nested braces.
     */
    private const LINE_PATTERN = '/^\[(?<datetime>\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[+-]\d{2}:?\d{2}|Z)?)\]\s+(?<channel>[\w.-]+)\.(?<level>\w+):\s+(?<rest>.+)$/';

    public function parse(string $line, ?string $sourceFile = null, ?int $lineNumber = null): ?LogEntry
    {
        $line = trim($line);

        if ('' === $line) {
            return null;
        }

        return $this->tryParseJson($line, $sourceFile, $lineNumber)
            ?? $this->tryParseText($line, $sourceFile, $lineNumber);
    }

    private function tryParseJson(string $line, ?string $sourceFile, ?int $lineNumber): ?LogEntry
    {
        if (!str_starts_with($line, '{')) {
            return null;
        }

        try {
            /** @var array<string, mixed>|mixed $data */
            $data = json_decode($line, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!\is_array($data)) {
            return null;
        }

        $datetime = $this->extractDateTime($data);
        if (null === $datetime) {
            return null;
        }

        $channel = $data['channel'] ?? $data['channel_name'] ?? 'app';
        $channelStr = \is_string($channel) ? $channel : (\is_scalar($channel) ? (string) $channel : 'app');

        $level = $data['level'] ?? $data['level_name'] ?? 'INFO';
        if (\is_int($level)) {
            $levelStr = $this->levelNumberToName($level);
        } else {
            $levelStr = \is_string($level) ? $level : (\is_scalar($level) ? (string) $level : 'INFO');
        }

        $message = $data['message'] ?? $data['msg'] ?? '';
        $messageStr = \is_string($message) ? $message : (\is_scalar($message) ? (string) $message : '');

        $contextRaw = $data['context'] ?? [];
        $extraRaw = $data['extra'] ?? [];

        /** @var array<string, mixed> $context */
        $context = \is_array($contextRaw) ? $contextRaw : [];
        /** @var array<string, mixed> $extra */
        $extra = \is_array($extraRaw) ? $extraRaw : [];

        return new LogEntry(
            datetime: $datetime,
            channel: $channelStr,
            level: strtoupper($levelStr),
            message: $messageStr,
            context: $context,
            extra: $extra,
            sourceFile: $sourceFile,
            lineNumber: $lineNumber,
        );
    }

    private function tryParseText(string $line, ?string $sourceFile, ?int $lineNumber): ?LogEntry
    {
        if (!preg_match(self::LINE_PATTERN, $line, $matches)) {
            return null;
        }

        $datetime = $this->parseDateTime($matches['datetime']);
        if (null === $datetime) {
            return null;
        }

        [$message, $context, $extra] = $this->parseMessageAndJson($matches['rest']);

        return new LogEntry(
            datetime: $datetime,
            channel: $matches['channel'],
            level: strtoupper($matches['level']),
            message: $message,
            context: $context,
            extra: $extra,
            sourceFile: $sourceFile,
            lineNumber: $lineNumber,
        );
    }

    /**
     * Parse message and trailing JSON objects from a log line rest.
     *
     * @return array{0: string, 1: array<string, mixed>, 2: array<string, mixed>}
     */
    private function parseMessageAndJson(string $rest): array
    {
        $rest = trim($rest);
        $context = [];
        $extra = [];
        $message = $rest;

        // Try to extract JSON objects from the end of the line
        // Pattern: "message text {...} {...}" or "message text {...} []" or "message text [] []"
        $jsonObjects = [];
        $workingString = $rest;

        for ($i = 0; $i < 2; ++$i) {
            $extracted = $this->extractTrailingJson($workingString);
            if (null === $extracted) {
                break;
            }

            [$json, $remaining] = $extracted;
            array_unshift($jsonObjects, $json);
            $workingString = $remaining;
        }

        if (2 === \count($jsonObjects)) {
            $message = trim($workingString);
            $context = $jsonObjects[0];
            $extra = $jsonObjects[1];
        } elseif (1 === \count($jsonObjects)) {
            $message = trim($workingString);
            $context = $jsonObjects[0];
        }

        return [$message, $context, $extra];
    }

    /**
     * @return array{0: array<string, mixed>, 1: string}|null Returns [parsed_json, remaining_string] or null
     */
    private function extractTrailingJson(string $str): ?array
    {
        $str = rtrim($str);

        if ('' === $str) {
            return null;
        }

        $lastChar = $str[-1];

        if ('}' !== $lastChar && ']' !== $lastChar) {
            return null;
        }

        $closingChar = $lastChar;
        $openingChar = '}' === $closingChar ? '{' : '[';
        $depth = 0;
        $inString = false;
        $escape = false;
        $startPos = null;

        for ($i = \strlen($str) - 1; $i >= 0; --$i) {
            $char = $str[$i];

            if ($escape) {
                $escape = false;
                continue;
            }

            if ('\\' === $char && $inString) {
                $escape = true;
                continue;
            }

            if ('"' === $char) {
                $inString = !$inString;
                continue;
            }

            if ($inString) {
                continue;
            }

            if ($char === $closingChar) {
                ++$depth;
            } elseif ($char === $openingChar) {
                --$depth;
                if (0 === $depth) {
                    $startPos = $i;
                    break;
                }
            }
        }

        if (null === $startPos) {
            return null;
        }

        if ($startPos > 0 && ' ' !== $str[$startPos - 1]) {
            return null;
        }

        $jsonStr = substr($str, $startPos);
        $remaining = substr($str, 0, $startPos);

        if ('[]' === $jsonStr || '{}' === $jsonStr) {
            return [[], rtrim($remaining)];
        }

        try {
            $parsed = json_decode($jsonStr, true, 512, \JSON_THROW_ON_ERROR);
            if (\is_array($parsed)) {
                /** @var array<string, mixed> $validatedParsed */
                $validatedParsed = $parsed;

                return [$validatedParsed, rtrim($remaining)];
            }

            return null;
        } catch (\JsonException) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractDateTime(array $data): ?\DateTimeImmutable
    {
        $datetimeValue = $data['datetime'] ?? $data['timestamp'] ?? $data['time'] ?? $data['@timestamp'] ?? null;

        if (null === $datetimeValue) {
            return null;
        }

        if (\is_array($datetimeValue)) {
            $dateStr = $datetimeValue['date'] ?? null;
            if (\is_string($dateStr)) {
                return $this->parseDateTime($dateStr);
            }

            return null;
        }

        // Handle string format
        if (\is_string($datetimeValue)) {
            return $this->parseDateTime($datetimeValue);
        }

        // Handle Unix timestamp
        if (\is_int($datetimeValue) || \is_float($datetimeValue)) {
            return (new \DateTimeImmutable())->setTimestamp((int) $datetimeValue);
        }

        return null;
    }

    private function parseDateTime(string $datetime): ?\DateTimeImmutable
    {
        $formats = [
            'Y-m-d H:i:s.u',
            'Y-m-d H:i:s',
            'Y-m-d\TH:i:s.uP',
            'Y-m-d\TH:i:sP',
            'Y-m-d\TH:i:s.u',
            'Y-m-d\TH:i:s',
            \DateTimeInterface::ATOM,
            \DateTimeInterface::RFC3339,
            \DateTimeInterface::RFC3339_EXTENDED,
        ];

        foreach ($formats as $format) {
            $parsed = \DateTimeImmutable::createFromFormat($format, $datetime);
            if (false !== $parsed) {
                return $parsed;
            }
        }

        try {
            return new \DateTimeImmutable($datetime);
        } catch (\Exception) {
            return null;
        }
    }

    private function levelNumberToName(int $level): string
    {
        return match ($level) {
            100 => 'DEBUG',
            200 => 'INFO',
            250 => 'NOTICE',
            300 => 'WARNING',
            400 => 'ERROR',
            500 => 'CRITICAL',
            550 => 'ALERT',
            600 => 'EMERGENCY',
            default => 'INFO',
        };
    }
}
