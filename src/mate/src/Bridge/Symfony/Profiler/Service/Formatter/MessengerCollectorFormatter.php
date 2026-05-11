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
use Symfony\Component\Messenger\DataCollector\MessengerDataCollector;

/**
 * Formats messenger collector data.
 *
 * Reports dispatched messages per bus with stamp types and exception presence.
 * Message payload values are intentionally excluded to avoid exposing PII or secrets.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 *
 * @internal
 *
 * @implements CollectorFormatterInterface<MessengerDataCollector>
 */
final class MessengerCollectorFormatter implements CollectorFormatterInterface
{
    private const MAX_MESSAGES = 50;

    public function getName(): string
    {
        return 'messenger';
    }

    public function format(DataCollectorInterface $collector): array
    {
        \assert($collector instanceof MessengerDataCollector);

        $buses = $collector->getBuses();
        $rawMessages = $collector->getMessages(null);
        $truncated = \count($rawMessages) > self::MAX_MESSAGES;
        $rawMessages = \array_slice($rawMessages, 0, self::MAX_MESSAGES);

        return [
            'bus_count' => \count($buses),
            'buses' => $buses,
            'message_count' => \count($collector->getMessages(null)),
            'exception_count' => $collector->getExceptionsCount(null),
            'messages' => $this->formatMessages($rawMessages),
            'messages_truncated' => $truncated,
        ];
    }

    public function getSummary(DataCollectorInterface $collector): array
    {
        \assert($collector instanceof MessengerDataCollector);

        return [
            'buses' => $collector->getBuses(),
            'message_count' => \count($collector->getMessages(null)),
            'exception_count' => $collector->getExceptionsCount(null),
        ];
    }

    /**
     * @param array<mixed> $messages
     *
     * @return list<array<string, mixed>>
     */
    private function formatMessages(array $messages): array
    {
        $formatted = [];
        foreach ($messages as $message) {
            if (\is_object($message) && method_exists($message, 'getValue')) {
                $message = $message->getValue(true);
            }

            if (!\is_array($message)) {
                continue;
            }

            $messageEntry = $message['message'] ?? [];
            if (\is_object($messageEntry) && method_exists($messageEntry, 'getValue')) {
                $messageEntry = $messageEntry->getValue(true);
            }

            $messageType = null;
            if (\is_array($messageEntry)) {
                $type = $messageEntry['type'] ?? null;
                $messageType = \is_object($type) ? (string) $type : (\is_string($type) ? $type : null);
            }

            $stamps = $message['stamps'] ?? [];
            if (\is_object($stamps) && method_exists($stamps, 'getValue')) {
                $stamps = $stamps->getValue(true);
            }
            $stampTypes = \is_array($stamps) ? array_keys($stamps) : [];

            $caller = $message['caller'] ?? [];
            if (\is_object($caller) && method_exists($caller, 'getValue')) {
                $caller = $caller->getValue(true);
            }

            $exception = $message['exception'] ?? null;
            if (\is_object($exception) && method_exists($exception, 'getValue')) {
                $exception = $exception->getValue(true);
            }

            $exceptionType = null;
            if (\is_array($exception)) {
                $type = $exception['type'] ?? null;
                $exceptionType = \is_object($type) ? (string) $type : (\is_string($type) ? $type : null);
            }

            $formatted[] = [
                'bus' => $message['bus'] ?? null,
                'message_type' => $messageType,
                'caller_name' => \is_array($caller) ? ($caller['name'] ?? null) : null,
                'caller_file' => \is_array($caller) ? ($caller['file'] ?? null) : null,
                'caller_line' => \is_array($caller) ? ($caller['line'] ?? null) : null,
                'stamp_count' => \count($stampTypes),
                'stamps' => $stampTypes,
                'has_exception' => null !== $exception && false !== $exception,
                'exception_type' => $exceptionType,
            ];
        }

        return $formatted;
    }
}
