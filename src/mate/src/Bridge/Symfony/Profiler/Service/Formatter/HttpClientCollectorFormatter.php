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
use Symfony\Component\HttpClient\DataCollector\HttpClientDataCollector;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 *
 * @internal
 *
 * @implements CollectorFormatterInterface<HttpClientDataCollector>
 */
final class HttpClientCollectorFormatter implements CollectorFormatterInterface
{
    private const MAX_CALLS = 50;
    private const MAX_ERROR_LENGTH = 300;
    private const SENSITIVE_NAME_PATTERN = '/token|key|secret|passw|pwd|auth|credential|signature|sig$|session|jwt|bearer|otp|code$/i';

    public function getName(): string
    {
        return 'http_client';
    }

    public function format(DataCollectorInterface $collector): array
    {
        \assert($collector instanceof HttpClientDataCollector);

        $calls = $this->collectCalls($collector);

        // Slowest first, so truncation drops the least interesting calls
        usort($calls, static fn (array $a, array $b): int => $b['total_time_ms'] <=> $a['total_time_ms']);

        $truncated = \count($calls) > self::MAX_CALLS;

        return [
            'request_count' => \count($calls),
            'failed_count' => $this->countFailed($calls),
            'total_time_ms' => $this->sumTime($calls),
            'calls' => \array_slice($calls, 0, self::MAX_CALLS),
            'calls_truncated' => $truncated,
        ];
    }

    public function getSummary(DataCollectorInterface $collector): array
    {
        \assert($collector instanceof HttpClientDataCollector);

        $calls = $this->collectCalls($collector);

        $slowest = null;
        foreach ($calls as $call) {
            if (null === $slowest || $call['total_time_ms'] > $slowest['total_time_ms']) {
                $slowest = $call;
            }
        }

        return [
            'request_count' => \count($calls),
            'total_time_ms' => $this->sumTime($calls),
            'failed_count' => $this->countFailed($calls),
            'slowest' => null === $slowest ? null : [
                'url' => $slowest['url'],
                'total_time_ms' => $slowest['total_time_ms'],
            ],
        ];
    }

    /**
     * @return list<array{client: string, method: string, url: string, http_code: int, total_time_ms: int, failed: bool, error: string|null, redirect_count: int|null}>
     */
    private function collectCalls(HttpClientDataCollector $collector): array
    {
        $calls = [];

        foreach ($collector->getClients() as $clientName => $client) {
            if (!\is_array($client)) {
                continue;
            }

            foreach ($client['traces'] ?? [] as $trace) {
                if (!\is_array($trace)) {
                    continue;
                }

                $calls[] = $this->formatTrace((string) $clientName, $trace);
            }
        }

        return $calls;
    }

    /**
     * @param array<string, mixed> $trace
     *
     * @return array{client: string, method: string, url: string, http_code: int, total_time_ms: int, failed: bool, error: string|null, redirect_count: int|null}
     */
    private function formatTrace(string $clientName, array $trace): array
    {
        $info = $this->toArray($trace['info'] ?? []);
        $debugInfo = $this->toArray($info['info'] ?? []);

        $httpCode = $this->toInt($trace['http_code'] ?? $debugInfo['http_code'] ?? 0);
        // Older Symfony versions keep total_time in the cloned info instead of on the trace
        $seconds = $trace['total_time'] ?? $debugInfo['total_time'] ?? $info['total_time'] ?? 0;
        $error = $this->formatError($info['error'] ?? null);
        $redirectCount = $this->toInt($info['redirect_count'] ?? 0);

        return [
            'client' => $clientName,
            'method' => \is_string($trace['method'] ?? null) ? strtoupper($trace['method']) : '',
            'url' => $this->sanitizeUrl($this->resolveUrl($trace, $info)),
            'http_code' => $httpCode,
            'total_time_ms' => (int) round((is_numeric($seconds) ? (float) $seconds : 0.0) * 1000),
            'failed' => 0 === $httpCode || $httpCode >= 400 || null !== $error,
            'error' => $error,
            'redirect_count' => $redirectCount > 0 ? $redirectCount : null,
        ];
    }

    /**
     * @param array<string, mixed> $trace
     * @param array<string, mixed> $info
     */
    private function resolveUrl(array $trace, array $info): string
    {
        // Scoped clients trace the relative URL, the resolved one only exists in the response info
        $candidates = [$info['original_url'] ?? null, $trace['url'] ?? null, $info['url'] ?? null];

        foreach ($candidates as $candidate) {
            if ($candidate instanceof Data) {
                $candidate = $candidate->getValue();
            }

            if (\is_string($candidate) && isset(parse_url($candidate)['host'])) {
                return $candidate;
            }
        }

        return \is_string($trace['url'] ?? null) ? $trace['url'] : '';
    }

    /**
     * @param list<array{total_time_ms: int, failed: bool}> $calls
     */
    private function countFailed(array $calls): int
    {
        return \count(array_filter($calls, static fn (array $call): bool => $call['failed']));
    }

    /**
     * @param list<array{total_time_ms: int, failed: bool}> $calls
     */
    private function sumTime(array $calls): int
    {
        return array_sum(array_column($calls, 'total_time_ms'));
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(mixed $value): array
    {
        if ($value instanceof Data) {
            $value = $value->getValue(true);
        }

        return \is_array($value) ? $value : [];
    }

    private function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function formatError(mixed $error): ?string
    {
        if ($error instanceof Data) {
            $error = $error->getValue();
        }

        if (!\is_string($error) || '' === $error) {
            return null;
        }

        // Error messages quote the requested URL, which can carry the same secrets
        $error = preg_replace_callback(
            '#https?://[^\s"\'<>]+#i',
            fn (array $match): string => $this->sanitizeUrl($match[0]),
            $error
        ) ?? '';

        return mb_strimwidth($error, 0, self::MAX_ERROR_LENGTH, '...');
    }

    private function sanitizeUrl(string $url): string
    {
        $parts = parse_url($url);
        if (false === $parts || !isset($parts['host'])) {
            return '***';
        }

        $scheme = isset($parts['scheme']) ? $parts['scheme'].'://' : '//';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $this->maskPathTokens($parts['path'] ?? '');
        $query = isset($parts['query']) ? $this->maskQuery($parts['query']) : '';

        return $scheme.$parts['host'].$port.$path.('' !== $query ? '?'.$query : '');
    }

    private function maskQuery(string $query): string
    {
        $pairs = [];
        foreach (explode('&', $query) as $pair) {
            if ('' === $pair) {
                continue;
            }

            if (!str_contains($pair, '=')) {
                $pairs[] = $pair;
                continue;
            }

            [$name, $value] = explode('=', $pair, 2);

            if ('' !== $value && (1 === preg_match(self::SENSITIVE_NAME_PATTERN, urldecode($name)) || $this->looksLikeToken(urldecode($value)))) {
                $value = '***';
            }

            $pairs[] = $name.'='.$value;
        }

        return implode('&', $pairs);
    }

    private function maskPathTokens(string $path): string
    {
        $segments = explode('/', $path);
        foreach ($segments as $index => $segment) {
            if ($this->looksLikeToken($segment)) {
                $segments[$index] = '***';
            }
        }

        return implode('/', $segments);
    }

    private function looksLikeToken(string $value): bool
    {
        if (\strlen($value) < 16) {
            return false;
        }

        // Lowercase slugs are kept, hex, base64, UUIDs and mixed-case strings are tokens
        return 1 !== preg_match('/^[a-z][a-z0-9]*(?:[_-][a-z][a-z0-9]*)+$/', $value);
    }
}
