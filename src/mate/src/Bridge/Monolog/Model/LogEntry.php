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
    private const REGEX_BACKTRACK_LIMIT = 10000;

    /**
     * Key-name substrings (case-insensitive) used to redact values in the log
     * context/extra exposed to the AI. Application code routinely logs tokens,
     * credentials, session identifiers and auth details there. Bare `KEY` and
     * `AUTH` are excluded to avoid redacting common non-sensitive keys (`key`,
     * `author`); the narrower `AUTHORIZATION`/`AUTHENTICATION`/`OAUTH` cover the
     * real cases.
     *
     * @var array<string>
     */
    private const SENSITIVE_KEY_PATTERNS = [
        'PASSWORD',
        'PASSWD',
        'PASSPHRASE',
        'PWD',
        'SECRET',
        'TOKEN',
        'JWT',
        'API_KEY',
        'APIKEY',
        'ACCESS_KEY',
        'SIGNING_KEY',
        'ENCRYPTION_KEY',
        'OAUTH',
        'AUTHORIZATION',
        'AUTHENTICATION',
        'CREDENTIAL',
        'PRIVATE',
        'BEARER',
        'CSRF',
        'XSRF',
        'SESSION',
        'SESSID',
        'SIGNATURE',
        'SALT',
        'COOKIE',
        'OTP',
    ];

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
            'context' => $this->redactSensitive($this->context),
            'extra' => $this->redactSensitive($this->extra),
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

        // Lower the PCRE backtrack limit temporarily so a model-supplied
        // catastrophic pattern cannot stall the worker (regex injection / ReDoS).
        $previousLimit = ini_set('pcre.backtrack_limit', self::REGEX_BACKTRACK_LIMIT);
        try {
            return (bool) @preg_match($pattern, $searchable);
        } finally {
            if (false !== $previousLimit) {
                ini_set('pcre.backtrack_limit', $previousLimit);
            }
        }
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

    /**
     * Recursively redact values whose key matches a sensitive pattern. Applied
     * only to the AI-facing output; the raw context/extra remain searchable.
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private function redactSensitive(array $data): array
    {
        foreach ($data as $key => $value) {
            if (\is_string($key) && $this->isSensitiveKey($key)) {
                $data[$key] = '***REDACTED***';
                continue;
            }

            if (\is_array($value)) {
                $data[$key] = $this->redactSensitive($value);
            }
        }

        return $data;
    }

    private function isSensitiveKey(string $key): bool
    {
        // Normalise hyphens to underscores so `api-key` matches the patterns too.
        $upperKey = str_replace('-', '_', strtoupper($key));
        foreach (self::SENSITIVE_KEY_PATTERNS as $pattern) {
            if (str_contains($upperKey, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
