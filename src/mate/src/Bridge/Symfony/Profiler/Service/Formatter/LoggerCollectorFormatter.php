<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter;

use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\CollectorFormatterInterface;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\DataCollector\LoggerDataCollector;

/**
 * Formats logger collector data.
 *
 * Reports log counts by severity and individual log entries (capped at MAX_LOGS)
 * with message and context extracted from VarDumper Data objects.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 *
 * @internal
 *
 * @implements CollectorFormatterInterface<LoggerDataCollector>
 */
final class LoggerCollectorFormatter implements CollectorFormatterInterface
{
    private const MAX_LOGS = 100;

    /**
     * Key-name substrings (case-insensitive) used to redact values in the log
     * context, where application code routinely puts tokens, credentials, session
     * identifiers and auth details. Bare `KEY` and `AUTH` are intentionally
     * excluded to avoid redacting common non-sensitive keys (`key`, `author`);
     * the narrower `AUTHORIZATION`/`AUTHENTICATION`/`OAUTH` cover the real cases.
     *
     * @var array<string>
     */
    private const SENSITIVE_CONTEXT_KEY_PATTERNS = [
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

    public function getName(): string
    {
        return 'logger';
    }

    public function format(DataCollectorInterface $collector): array
    {
        \assert($collector instanceof LoggerDataCollector);

        $processedLogs = $collector->getProcessedLogs();
        $truncated = \count($processedLogs) > self::MAX_LOGS;
        $processedLogs = \array_slice($processedLogs, 0, self::MAX_LOGS);

        return [
            'error_count' => $collector->countErrors(),
            'warning_count' => $collector->countWarnings(),
            'deprecation_count' => $collector->countDeprecations(),
            'scream_count' => $collector->countScreams(),
            'logs' => $this->formatLogs($processedLogs),
            'logs_truncated' => $truncated,
        ];
    }

    public function getSummary(DataCollectorInterface $collector): array
    {
        \assert($collector instanceof LoggerDataCollector);

        return [
            'error_count' => $collector->countErrors(),
            'warning_count' => $collector->countWarnings(),
            'deprecation_count' => $collector->countDeprecations(),
            'scream_count' => $collector->countScreams(),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $logs
     *
     * @return list<array<string, mixed>>
     */
    private function formatLogs(array $logs): array
    {
        $formatted = [];
        foreach ($logs as $log) {
            $message = $log['message'] ?? null;
            if (\is_object($message) && method_exists($message, 'getValue')) {
                $message = $message->getValue();
            }

            $context = $log['context'] ?? null;
            if (\is_object($context) && method_exists($context, 'getValue')) {
                $context = $context->getValue(true);
            }

            if (\is_array($context)) {
                $context = $this->redactContext($context);
            }

            $formatted[] = [
                'type' => $log['type'] ?? null,
                'timestamp' => $log['timestamp'] ?? null,
                'priority' => $log['priority'] ?? null,
                'priority_name' => $log['priorityName'] ?? null,
                'channel' => $log['channel'] ?? null,
                'message' => $message,
                'context' => $context,
                'error_count' => $log['errorCount'] ?? null,
            ];
        }

        return $formatted;
    }

    /**
     * Recursively redact values whose key matches a sensitive pattern.
     *
     * @param array<array-key, mixed> $context
     *
     * @return array<array-key, mixed>
     */
    private function redactContext(array $context): array
    {
        foreach ($context as $key => $value) {
            if (\is_string($key) && $this->isSensitiveContextKey($key)) {
                $context[$key] = '***REDACTED***';
                continue;
            }

            if (\is_array($value)) {
                $context[$key] = $this->redactContext($value);
            }
        }

        return $context;
    }

    private function isSensitiveContextKey(string $key): bool
    {
        // Normalise hyphens to underscores so `api-key` matches the patterns too.
        $upperKey = str_replace('-', '_', strtoupper($key));
        foreach (self::SENSITIVE_CONTEXT_KEY_PATTERNS as $pattern) {
            if (str_contains($upperKey, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
