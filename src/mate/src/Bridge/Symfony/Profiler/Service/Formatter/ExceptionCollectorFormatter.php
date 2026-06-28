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
 * Exception messages frequently embed secrets (DSN/connection-string passwords,
 * `token=...` fragments, Authorization headers), so high-confidence secret
 * shapes are scrubbed before the message reaches the AI. The class, file, line
 * and trace — the actionable parts — are kept as-is.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 *
 * @internal
 *
 * @implements CollectorFormatterInterface<ExceptionDataCollector>
 */
final class ExceptionCollectorFormatter implements CollectorFormatterInterface
{
    /**
     * Ordered regex => replacement pairs scrubbing high-confidence secret shapes
     * from free-text exception messages.
     *
     * @var array<string, string>
     */
    private const SECRET_MESSAGE_PATTERNS = [
        // DSN / URL userinfo password: scheme://[user]:pass@host (the user may be
        // empty, e.g. Redis/AMQP DSNs like redis://:pass@host).
        '#([a-z][a-z0-9+.\-]*://[^/\s:@]*:)[^/\s@]+(@)#i' => '$1***REDACTED***$2',
        // Authorization scheme tokens (Bearer / Basic / Digest).
        '/\b(Bearer|Basic|Digest)\s+[A-Za-z0-9+\/=._\-]+/i' => '$1 ***REDACTED***',
        // sensitive key=value / key: value. The sensitive word may sit anywhere
        // in the key (e.g. client_secret, secret_key); the separator allows the
        // quoting of JSON (`"password":"x"`); the value may be a quoted run
        // (incl. spaces) or an unquoted token up to the next delimiter. The colon
        // must not be a `::` (PHP scope resolution) to avoid eating class names.
        '/([\w.\-]*(?:password|passwd|pwd|secret|token|api[_-]?key|access[_-]?key|private[_-]?key|signing[_-]?key|encryption[_-]?key|credential|authorization)[\w.\-]*)(["\']?\s*(?:=|:(?!:))\s*)(?:"[^"]*"|\'[^\']*\'|[^\s,;]+)/i' => '$1$2***REDACTED***',
    ];

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
            'message' => $this->scrubMessage($collector->getMessage()),
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
            'message' => $this->scrubMessage($collector->getMessage()),
            'class' => $exception->getClass(),
        ];
    }

    private function scrubMessage(string $message): string
    {
        foreach (self::SECRET_MESSAGE_PATTERNS as $pattern => $replacement) {
            $result = preg_replace($pattern, $replacement, $message);
            if (null === $result) {
                // PCRE failure (e.g. backtrack limit on a pathological message):
                // fail closed rather than returning the original, un-scrubbed text.
                return '***REDACTED*** (exception message withheld: scrubbing failed)';
            }

            $message = $result;
        }

        return $message;
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
