<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Monolog\Model;

/**
 * Represents a single log entry parsed from a Monolog log file.
 *
 * @phpstan-type LogEntryArray array{
 *     datetime: string,
 *     channel: string,
 *     level: string,
 *     message: string,
 *     context: array<string, mixed>,
 *     extra: array<string, mixed>,
 *     source_file: string|null,
 *     line_number: int|null
 * }
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class LogEntry
{
    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $extra
     */
    public function __construct(
        private readonly \DateTimeImmutable $datetime,
        private readonly string $channel,
        private readonly string $level,
        private readonly string $message,
        private readonly array $context = [],
        private readonly array $extra = [],
        private readonly ?string $sourceFile = null,
        private readonly ?int $lineNumber = null,
    ) {
    }

    public function getDatetime(): \DateTimeImmutable
    {
        return $this->datetime;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtra(): array
    {
        return $this->extra;
    }

    public function getSourceFile(): ?string
    {
        return $this->sourceFile;
    }

    public function getLineNumber(): ?int
    {
        return $this->lineNumber;
    }

    /**
     * @phpstan-return LogEntryArray
     */
    public function toArray(): array
    {
        return [
            'datetime' => $this->datetime->format(\DateTimeInterface::ATOM),
            'channel' => $this->channel,
            'level' => $this->level,
            'message' => $this->message,
            'context' => $this->context,
            'extra' => $this->extra,
            'source_file' => $this->sourceFile,
            'line_number' => $this->lineNumber,
        ];
    }

    public function matchesTerm(string $term): bool
    {
        $searchable = strtolower($this->message.' '.json_encode($this->context).' '.json_encode($this->extra));

        return str_contains($searchable, strtolower($term));
    }

    public function matchesRegex(string $pattern): bool
    {
        $searchable = $this->message.' '.json_encode($this->context).' '.json_encode($this->extra);

        return (bool) preg_match($pattern, $searchable);
    }

    public function hasContextValue(string $key, string $value): bool
    {
        if (!isset($this->context[$key])) {
            return false;
        }

        $contextValue = $this->context[$key];
        if (\is_string($contextValue)) {
            return str_contains(strtolower($contextValue), strtolower($value));
        }

        if (\is_scalar($contextValue)) {
            return strtolower((string) $contextValue) === strtolower($value);
        }

        return str_contains(strtolower(json_encode($contextValue) ?: ''), strtolower($value));
    }
}
