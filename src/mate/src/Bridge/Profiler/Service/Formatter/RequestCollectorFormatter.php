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

        $class = new \ReflectionClass($collectorData);
        $property = $class->getProperty('data');
        $property->setAccessible(true);
        $data = $property->getValue($collectorData);

        return $data->getValue(true);
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
}
