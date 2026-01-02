<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Profiler\Service\Formatter;

use Symfony\AI\Mate\Bridge\Profiler\Service\CollectorFormatterInterface;

/**
 * Formats HTTP request collector data.
 *
 * Note: Symfony's RequestDataCollector contains both request AND response data,
 * plus session information. There is no separate ResponseDataCollector.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 *
 * @internal
 */
final class RequestCollectorFormatter implements CollectorFormatterInterface
{
    public function getName(): string
    {
        return 'request';
    }

    public function format(mixed $collectorData): array
    {
        if (!\is_object($collectorData)) {
            return [];
        }

        $formatted = [
            'request' => [],
            'response' => [],
            'session' => [],
            'routing' => [],
        ];

        // Request data
        if (method_exists($collectorData, 'getMethod')) {
            $formatted['request']['method'] = $collectorData->getMethod();
        }

        if (method_exists($collectorData, 'getPathInfo')) {
            $formatted['request']['path'] = $collectorData->getPathInfo();
        }

        if (method_exists($collectorData, 'getRequestHeaders')) {
            $formatted['request']['headers'] = $this->formatHeaders($collectorData->getRequestHeaders());
        }

        if (method_exists($collectorData, 'getRequestRequest')) {
            $formatted['request']['request_parameters'] = $collectorData->getRequestRequest()->all();
        }

        if (method_exists($collectorData, 'getRequestQuery')) {
            $formatted['request']['query_parameters'] = $collectorData->getRequestQuery()->all();
        }

        if (method_exists($collectorData, 'getRequestAttributes')) {
            $formatted['request']['attributes'] = $collectorData->getRequestAttributes()->all();
        }

        if (method_exists($collectorData, 'getRequestCookies')) {
            $formatted['request']['cookies'] = $this->sanitizeCookies($collectorData->getRequestCookies());
        }

        if (method_exists($collectorData, 'getRequestServer')) {
            $formatted['request']['server'] = $collectorData->getRequestServer()->all();
        }

        if (method_exists($collectorData, 'getRequestFiles')) {
            $files = $collectorData->getRequestFiles();
            $formatted['request']['files'] = \is_object($files) && method_exists($files, 'all') ? $files->all() : $files;
        }

        if (method_exists($collectorData, 'getFormat')) {
            $formatted['request']['format'] = $collectorData->getFormat();
        }

        if (method_exists($collectorData, 'getLocale')) {
            $formatted['request']['locale'] = $collectorData->getLocale();
        }

        // Response data
        if (method_exists($collectorData, 'getStatusCode')) {
            $formatted['response']['status_code'] = $collectorData->getStatusCode();
        }

        if (method_exists($collectorData, 'getStatusText')) {
            $formatted['response']['status_text'] = $collectorData->getStatusText();
        }

        if (method_exists($collectorData, 'getContentType')) {
            $formatted['response']['content_type'] = $collectorData->getContentType();
        }

        if (method_exists($collectorData, 'getResponseHeaders')) {
            $formatted['response']['headers'] = $this->formatResponseHeaders($collectorData->getResponseHeaders());
        }

        if (method_exists($collectorData, 'getResponseCookies')) {
            $cookies = $collectorData->getResponseCookies();
            $formatted['response']['cookies'] = \is_object($cookies) && method_exists($cookies, 'all') ? $cookies->all() : $cookies;
        }

        // Session data
        if (method_exists($collectorData, 'getSessionMetadata')) {
            $formatted['session']['metadata'] = $collectorData->getSessionMetadata();
        }

        if (method_exists($collectorData, 'getSessionAttributes')) {
            $formatted['session']['attributes'] = $collectorData->getSessionAttributes();
        }

        if (method_exists($collectorData, 'getSessionUsages')) {
            $usages = $collectorData->getSessionUsages();
            $formatted['session']['usages'] = \is_array($usages) ? $usages : [];
        }

        if (method_exists($collectorData, 'getStatelessCheck')) {
            $formatted['session']['stateless'] = $collectorData->getStatelessCheck();
        }

        if (method_exists($collectorData, 'getFlashes')) {
            $formatted['session']['flashes'] = $collectorData->getFlashes();
        }

        // Routing data
        if (method_exists($collectorData, 'getRoute')) {
            $formatted['routing']['route'] = $collectorData->getRoute();
        }

        if (method_exists($collectorData, 'getRouteParams')) {
            $formatted['routing']['params'] = $collectorData->getRouteParams();
        }

        if (method_exists($collectorData, 'getController')) {
            $controller = $collectorData->getController();
            $formatted['routing']['controller'] = \is_array($controller) || \is_string($controller) ? $controller : (string) $controller;
        }

        if (method_exists($collectorData, 'getRedirect')) {
            $redirect = $collectorData->getRedirect();
            if (false !== $redirect) {
                $formatted['routing']['redirect'] = \is_array($redirect) ? $redirect : [];
            }
        }

        if (method_exists($collectorData, 'getForwardToken')) {
            $forwardToken = $collectorData->getForwardToken();
            if (null !== $forwardToken) {
                $formatted['routing']['forward_token'] = $forwardToken;
            }
        }

        // Additional data
        if (method_exists($collectorData, 'getDotenvVars')) {
            $dotenvVars = $collectorData->getDotenvVars();
            $formatted['dotenv_vars'] = \is_object($dotenvVars) && method_exists($dotenvVars, 'all') ? $dotenvVars->all() : $dotenvVars;
        }

        return array_filter($formatted, fn ($section) => [] !== $section);
    }

    public function getSummary(mixed $collectorData): array
    {
        $summary = [];

        if (method_exists($collectorData, 'getMethod')) {
            $summary['method'] = $collectorData->getMethod();
        }

        if (method_exists($collectorData, 'getPathInfo')) {
            $summary['path'] = $collectorData->getPathInfo();
        }

        if (method_exists($collectorData, 'getRoute')) {
            $summary['route'] = $collectorData->getRoute();
        }

        if (method_exists($collectorData, 'getStatusCode')) {
            $summary['status_code'] = $collectorData->getStatusCode();
        }

        if (method_exists($collectorData, 'getContentType')) {
            $summary['content_type'] = $collectorData->getContentType();
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatHeaders(mixed $headers): array
    {
        $formatted = [];

        if (!\is_object($headers) || !method_exists($headers, 'all')) {
            return $formatted;
        }

        foreach ($headers->all() as $name => $values) {
            $formatted[$name] = \is_array($values) ? implode(', ', $values) : $values;
        }

        return $formatted;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatResponseHeaders(mixed $headers): array
    {
        $formatted = [];

        if (!\is_object($headers) || !method_exists($headers, 'all')) {
            return $formatted;
        }

        foreach ($headers->all() as $name => $values) {
            $formatted[$name] = \is_array($values) ? implode(', ', $values) : $values;
        }

        return $formatted;
    }

    /**
     * @return array<string, mixed>
     */
    private function sanitizeCookies(mixed $cookies): array
    {
        $sanitized = [];

        if (!\is_object($cookies) || !method_exists($cookies, 'all')) {
            return $sanitized;
        }

        foreach ($cookies->all() as $name => $value) {
            if (str_contains(strtolower($name), 'token') || str_contains(strtolower($name), 'session')) {
                $sanitized[$name] = '[REDACTED]';
            } else {
                $sanitized[$name] = $value;
            }
        }

        return $sanitized;
    }
}
