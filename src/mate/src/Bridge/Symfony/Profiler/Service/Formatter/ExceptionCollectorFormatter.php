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
use Symfony\Component\HttpKernel\DataCollector\ExceptionDataCollector;

/**
 * Formats exception collector data.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 *
 * @internal
 *
 * @implements CollectorFormatterInterface<ExceptionDataCollector>
 */
final class ExceptionCollectorFormatter implements CollectorFormatterInterface
{
    public function getName(): string
    {
        return 'exception';
    }

    public function format(DataCollectorInterface $collector): array
    {
        \assert($collector instanceof ExceptionDataCollector);

        if (!$collector->hasException()) {
            return [
                'has_exception' => false,
            ];
        }

        $exception = $collector->getException();

        return [
            'has_exception' => true,
            'message' => $collector->getMessage(),
            'status_code' => $collector->getStatusCode(),
            'class' => $exception->getClass(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $this->formatTrace($exception->getTrace()),
        ];
    }

    public function getSummary(DataCollectorInterface $collector): array
    {
        \assert($collector instanceof ExceptionDataCollector);

        if (!$collector->hasException()) {
            return [
                'has_exception' => false,
            ];
        }

        $exception = $collector->getException();

        return [
            'has_exception' => true,
            'message' => $collector->getMessage(),
            'class' => $exception->getClass(),
        ];
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
