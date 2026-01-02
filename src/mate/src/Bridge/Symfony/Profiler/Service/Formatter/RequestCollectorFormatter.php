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
 * - Password and authentication data
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
    ];

    /**
     * @var array<string>
     */
    private const SENSITIVE_HEADERS = [
        'authorization',
        'cookie',
        'set-cookie',
        'x-api-key',
        'x-auth-token',
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
            $data['request_server'] = $this->sanitizeEnvironment($data['request_server']);
        }

        // Sanitize dotenv vars
        if (isset($data['dotenv_vars']) && \is_array($data['dotenv_vars'])) {
            $data['dotenv_vars'] = $this->sanitizeEnvironment($data['dotenv_vars']);
        }

        // Sanitize session data
        if (isset($data['session_attributes']) && \is_array($data['session_attributes'])) {
            $data['session_attributes'] = $this->redactArray($data['session_attributes']);
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
            $lowerKey = strtolower($key);
            if (\in_array($lowerKey, self::SENSITIVE_HEADERS, true)) {
                $headers[$key] = '***REDACTED***';
            }
        }

        return $headers;
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
}
