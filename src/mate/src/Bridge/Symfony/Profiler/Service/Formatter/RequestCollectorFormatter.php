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
use Symfony\Component\HttpKernel\DataCollector\RequestDataCollector;

/**
 * Formats HTTP request collector data with security sanitization.
 *
 * Redacts sensitive information including:
 * - Session cookies and tokens
 * - API keys and secrets in environment variables
 * - Authorization headers
 * - Password and authentication data submitted as request/query parameters
 * - The raw request body (which may carry credentials, e.g. a JSON login payload)
 * - The reconstructed curl command (re-embeds body, URL query string and headers)
 *
 * @author Johannes Wachter <johannes@sulu.io>
 *
 * @internal
 *
 * @implements CollectorFormatterInterface<RequestDataCollector>
 */
final class RequestCollectorFormatter implements CollectorFormatterInterface
{
    /**
     * @var array<string>
     */
    private const SENSITIVE_ENV_PATTERNS = [
        'SECRET',
        'KEY',
        'PASSWORD',
        'TOKEN',
        'BEARER',
        'AUTH',
        'CREDENTIAL',
        'PRIVATE',
        'COOKIE',
    ];

    /**
     * Server-bag keys whose values embed sensitive request data (the raw query
     * string, in particular) under a non-sensitive key name, so key-based
     * matching would otherwise miss them.
     *
     * @var array<string>
     */
    private const SENSITIVE_SERVER_KEYS = [
        'REQUEST_URI',
        'QUERY_STRING',
    ];

    /**
     * Substrings that mark a header as sensitive (case-insensitive). Matching by
     * substring rather than an exact name list so variants and vendor-specific
     * headers (e.g. `proxy-authorization`, `www-authenticate`, `x-csrf-token`,
     * `x-amz-security-token`) are covered without enumerating every spelling.
     *
     * @var array<string>
     */
    private const SENSITIVE_HEADER_PATTERNS = [
        'authorization',
        'cookie',
        'auth',
        'token',
        'secret',
        'credential',
        'csrf',
        'xsrf',
        'api-key',
        'apikey',
        'session',
        'x-amz-security',
    ];

    /**
     * Parameter names whose values are redacted wherever they appear in request,
     * query, or attribute data (case-insensitive substring match). Symfony's own
     * collector only masks `_password`; any other field name (e.g. `password`,
     * `api_key`) and the whole query string otherwise reach us in clear text.
     *
     * @var array<string>
     */
    private const SENSITIVE_PARAM_PATTERNS = [
        'PASSWORD',
        'PASSWD',
        'PASSPHRASE',
        'PWD',
        'SECRET',
        'TOKEN',
        'API_KEY',
        'APIKEY',
        'ACCESS_KEY',
        'SIGNING_KEY',
        'ENCRYPTION_KEY',
        'OAUTH',
        'CREDENTIAL',
        'PRIVATE',
        'BEARER',
        'CSRF',
        'XSRF',
        'OTP',
    ];

    public function getName(): string
    {
        return 'request';
    }

    public function format(DataCollectorInterface $collectorData): array
    {
        \assert($collectorData instanceof RequestDataCollector);

        $class = new \ReflectionClass($collectorData);
        $property = $class->getProperty('data');
        $data = $property->getValue($collectorData);

        $formatted = $data->getValue(true);

        return $this->sanitizeData($formatted);
    }

    public function getSummary(DataCollectorInterface $collectorData): array
    {
        \assert($collectorData instanceof RequestDataCollector);

        return [
            'method' => $collectorData->getMethod(),
            'path' => $collectorData->getPathInfo(),
            'route' => $collectorData->getRoute(),
            'status_code' => $collectorData->getStatusCode(),
            'content_type' => $collectorData->getContentType(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function sanitizeData(array $data): array
    {
        // Sanitize cookies - redact all cookie values
        if (isset($data['request_cookies']) && \is_array($data['request_cookies'])) {
            $data['request_cookies'] = $this->redactArray($data['request_cookies']);
        }

        if (isset($data['response_cookies']) && \is_array($data['response_cookies'])) {
            $data['response_cookies'] = $this->redactArray($data['response_cookies']);
        }

        // Sanitize request headers
        if (isset($data['request_headers']) && \is_array($data['request_headers'])) {
            $data['request_headers'] = $this->sanitizeHeaders($data['request_headers']);
        }

        // Sanitize response headers
        if (isset($data['response_headers']) && \is_array($data['response_headers'])) {
            $data['response_headers'] = $this->sanitizeHeaders($data['response_headers']);
        }

        // Sanitize server variables (environment)
        if (isset($data['request_server']) && \is_array($data['request_server'])) {
            $data['request_server'] = $this->sanitizeServer($data['request_server']);
        }

        // Sanitize dotenv vars
        if (isset($data['dotenv_vars']) && \is_array($data['dotenv_vars'])) {
            $data['dotenv_vars'] = $this->sanitizeEnvironment($data['dotenv_vars']);
        }

        // Sanitize session data
        if (isset($data['session_attributes']) && \is_array($data['session_attributes'])) {
            $data['session_attributes'] = $this->redactArray($data['session_attributes']);
        }

        // Redact sensitive values submitted as request/query parameters or stored
        // as request attributes (e.g. passwords, API keys, CSRF tokens). Symfony
        // only masks `_password`; other field names and the query string would
        // otherwise be exposed verbatim.
        foreach (['request_request', 'request_query', 'request_attributes'] as $key) {
            if (isset($data[$key]) && \is_array($data[$key])) {
                $data[$key] = $this->redactSensitiveParams($data[$key]);
            }
        }

        // The raw request body can carry credentials (e.g. a JSON login payload)
        // that cannot be reliably field-redacted; omit it. Parsed, sanitized
        // parameters remain available under request_request / request_query.
        if (isset($data['content']) && \is_string($data['content']) && '' !== $data['content']) {
            $data['content'] = '***REDACTED*** (raw request body omitted; see request_request / request_query)';
        }

        // The reconstructed curl command re-embeds the raw body (--data-raw), the
        // full URL including the query string, and request headers (Authorization
        // included), so it would re-expose everything the redactions above remove.
        if (isset($data['curlCommand']) && \is_string($data['curlCommand'])) {
            $data['curlCommand'] = '***REDACTED*** (curl command omitted; reconstructs the raw body, URL query string and request headers)';
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $headers
     *
     * @return array<string, mixed>
     */
    private function sanitizeHeaders(array $headers): array
    {
        foreach ($headers as $key => $value) {
            if ($this->isSensitiveHeader($key)) {
                $headers[$key] = '***REDACTED***';
            }
        }

        return $headers;
    }

    private function isSensitiveHeader(string $name): bool
    {
        $lowerName = strtolower($name);
        foreach (self::SENSITIVE_HEADER_PATTERNS as $pattern) {
            if (str_contains($lowerName, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $env
     *
     * @return array<string, mixed>
     */
    private function sanitizeEnvironment(array $env): array
    {
        foreach ($env as $key => $value) {
            $upperKey = strtoupper($key);
            foreach (self::SENSITIVE_ENV_PATTERNS as $pattern) {
                if (str_contains($upperKey, $pattern)) {
                    $env[$key] = '***REDACTED***';
                    break;
                }
            }
        }

        return $env;
    }

    /**
     * @param array<string, mixed> $server
     *
     * @return array<string, mixed>
     */
    private function sanitizeServer(array $server): array
    {
        $server = $this->sanitizeEnvironment($server);

        foreach (self::SENSITIVE_SERVER_KEYS as $key) {
            if (isset($server[$key]) && '' !== $server[$key]) {
                $server[$key] = '***REDACTED***';
            }
        }

        return $server;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function redactArray(array $data): array
    {
        foreach ($data as $key => $value) {
            $data[$key] = '***REDACTED***';
        }

        return $data;
    }

    /**
     * Recursively redact values whose key matches a sensitive parameter pattern,
     * leaving non-sensitive keys (and the overall structure) intact so the AI can
     * still see which parameters were submitted.
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private function redactSensitiveParams(array $data): array
    {
        foreach ($data as $key => $value) {
            if (\is_string($key) && $this->isSensitiveParam($key)) {
                $data[$key] = '***REDACTED***';
                continue;
            }

            if (\is_array($value)) {
                $data[$key] = $this->redactSensitiveParams($value);
            }
        }

        return $data;
    }

    private function isSensitiveParam(string $key): bool
    {
        $upperKey = strtoupper($key);
        foreach (self::SENSITIVE_PARAM_PATTERNS as $pattern) {
            if (str_contains($upperKey, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
