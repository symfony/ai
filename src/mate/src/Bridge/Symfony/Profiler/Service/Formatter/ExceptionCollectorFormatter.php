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

/**
 * Formats exception collector data.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 *
 * @internal
 *
 * @implements CollectorFormatterInterface<DataCollectorInterface>
 */
final class ExceptionCollectorFormatter implements CollectorFormatterInterface
{
    public function getName(): string
    {
        return 'exception';
    }

    public function format(DataCollectorInterface $collectorData): array
    {
        if (!method_exists($collectorData, 'hasException') || !$collectorData->hasException()) {
            return [
                'has_exception' => false,
            ];
        }

        $formatted = [
            'has_exception' => true,
        ];

        if (method_exists($collectorData, 'getMessage')) {
            $formatted['message'] = $collectorData->getMessage();
        }

        if (method_exists($collectorData, 'getException')) {
            $exception = $collectorData->getException();
            if (\is_object($exception)) {
                if (method_exists($exception, 'getClass')) {
                    $formatted['class'] = $exception->getClass();
                }
                if (method_exists($exception, 'getFile')) {
                    $formatted['file'] = $exception->getFile();
                }
                if (method_exists($exception, 'getLine')) {
                    $formatted['line'] = $exception->getLine();
                }
                if (method_exists($exception, 'getTrace')) {
                    $formatted['trace'] = $this->formatTrace($exception->getTrace());
                }
            }
        }

        if (method_exists($collectorData, 'getStatusCode')) {
            $formatted['status_code'] = $collectorData->getStatusCode();
        }

        return $formatted;
    }

    public function getSummary(DataCollectorInterface $collectorData): array
    {
        if (!method_exists($collectorData, 'hasException') || !$collectorData->hasException()) {
            return [
                'has_exception' => false,
            ];
        }

        $summary = [
            'has_exception' => true,
        ];

        if (method_exists($collectorData, 'getMessage')) {
            $summary['message'] = $collectorData->getMessage();
        }

        if (method_exists($collectorData, 'getException')) {
            $exception = $collectorData->getException();
            if (\is_object($exception) && method_exists($exception, 'getClass')) {
                $summary['class'] = $exception->getClass();
            }
        }

        return $summary;
    }

    /**
     * @param array<array<string, mixed>> $trace
     *
     * @return array<array<string, mixed>>
     */
    private function formatTrace(array $trace): array
    {
        $formatted = [];
        $maxFrames = 10;

        foreach (\array_slice($trace, 0, $maxFrames) as $frame) {
            $formattedFrame = [];

            if (isset($frame['file'])) {
                $formattedFrame['file'] = $frame['file'];
            }

            if (isset($frame['line'])) {
                $formattedFrame['line'] = $frame['line'];
            }

            if (isset($frame['class'])) {
                $formattedFrame['class'] = $frame['class'];
            }

            if (isset($frame['function'])) {
                $formattedFrame['function'] = $frame['function'];
            }

            $formatted[] = $formattedFrame;
        }

        return $formatted;
    }
}
