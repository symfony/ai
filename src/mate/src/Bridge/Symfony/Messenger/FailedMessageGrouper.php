<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Messenger;

/**
 * Groups failed messages by cause: exception class, exception message with its variable parts
 * blanked, and the first application frame of the trace (the frame outside vendor/ that threw,
 * usually the handler).
 *
 * @internal
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class FailedMessageGrouper
{
    public const MAX_SAMPLES = 5;
    public const MAX_SAMPLE_LENGTH = 300;
    public const MAX_FRAMES = 6;
    public const MAX_CALL_LENGTH = 160;
    public const MAX_IDS = 20;

    /**
     * @param list<string> $projectDirs trace paths under these are shown relative
     */
    public function __construct(
        private readonly array $projectDirs = [],
    ) {
    }

    /**
     * Largest group first; groups of equal size by their latest failure. Every group is returned,
     * however small.
     *
     * @param list<FailedMessage> $messages
     * @param int|null            $only     return only this group (1-based), with every message listed
     *
     * @return list<array<string, mixed>>
     */
    public function group(array $messages, ?int $only = null): array
    {
        $groups = [];
        foreach ($messages as $message) {
            $frames = $this->frames($message);
            $failedIn = $this->failedIn($frames);
            $key = implode('|', [$message->exceptionClass, $this->pattern((string) $message->exceptionMessage), $failedIn]);
            $groups[$key]['frames'] ??= $frames;
            $groups[$key]['failed_in'] ??= $failedIn;
            $groups[$key]['messages'][] = $message;
        }

        $groups = array_values($groups);
        usort($groups, fn (array $a, array $b): int => [\count($b['messages']), $this->last($b['messages'])] <=> [\count($a['messages']), $this->last($a['messages'])]);

        $result = [];
        foreach ($groups as $i => $group) {
            if (null !== $only && $only !== $i + 1) {
                continue;
            }
            /** @var list<FailedMessage> $members */
            $members = $group['messages'];
            $retries = array_map(static fn (FailedMessage $m): int => $m->retryCount, $members);
            $first = array_filter(array_map(static fn (FailedMessage $m): ?\DateTimeImmutable => $m->firstFailedAt, $members));
            $last = array_filter(array_map(static fn (FailedMessage $m): ?\DateTimeImmutable => $m->lastFailedAt, $members));
            $ids = array_map(static fn (FailedMessage $m): string => $m->id, $members);

            $entry = [
                'group' => $i + 1,
                'count' => \count($members),
                'message_classes' => array_values(array_unique(array_map(static fn (FailedMessage $m): string => $m->messageClass, $members))),
                'exception_class' => $members[0]->exceptionClass,
                'failed_in' => $group['failed_in'],
                'sample_messages' => \array_slice(array_values(array_unique(array_map(fn (FailedMessage $m): string => $this->trim((string) $m->exceptionMessage, self::MAX_SAMPLE_LENGTH), $members))), 0, self::MAX_SAMPLES),
                'trace' => $this->trace($group['frames']),
                'retry_count' => min($retries) === max($retries) ? min($retries) : ['min' => min($retries), 'max' => max($retries)],
                'first_failed_at' => [] === $first ? null : min($first)->format(\DATE_ATOM),
                'last_failed_at' => [] === $last ? null : max($last)->format(\DATE_ATOM),
                'original_transports' => array_values(array_unique(array_filter(array_map(static fn (FailedMessage $m): ?string => $m->originalTransport, $members)))),
                'ids' => null === $only ? \array_slice($ids, 0, self::MAX_IDS) : $ids,
                'ids_truncated' => null === $only && \count($ids) > self::MAX_IDS,
            ];
            if (null !== $only) {
                $entry['messages'] = array_map(fn (FailedMessage $m): array => [
                    'id' => $m->id,
                    'class' => $m->messageClass,
                    'exception_message' => $this->trim((string) $m->exceptionMessage, self::MAX_SAMPLE_LENGTH),
                    'retry_count' => $m->retryCount,
                    'first_failed_at' => $m->firstFailedAt?->format(\DATE_ATOM),
                    'last_failed_at' => $m->lastFailedAt?->format(\DATE_ATOM),
                    'original_transport' => $m->originalTransport,
                ], $members);
            }
            $result[] = $entry;
        }

        return $result;
    }

    /**
     * The message with its variable parts (quoted values, paths, numbers) blanked, so
     * `Price "12,50" is invalid` and `Price "9,63" is invalid` fall into one group.
     */
    public function pattern(string $message): string
    {
        $message = preg_replace('/"[^"]*"|\'[^\']*\'/', '"…"', $message) ?? $message;
        $message = preg_replace('~(/[\w.\-]+)+~', '/…', $message) ?? $message;

        return preg_replace('/\d+/', 'N', $message) ?? $message;
    }

    /**
     * The throw site, then the frames of the stored trace string ("#0 /path/File.php(20): Class->method()").
     *
     * @return list<array{at: string, call: string|null, app: bool}>
     */
    private function frames(FailedMessage $message): array
    {
        $frames = [];
        if (null !== $message->file) {
            $frames[] = ['at' => \sprintf('%s:%d', $this->relative($message->file), $message->line ?? 0), 'call' => null, 'app' => $this->isApp($message->file)];
        }
        foreach (explode("\n", $message->traceAsString) as $line) {
            if (1 === preg_match('/^#\d+ (.+?)\((\d+)\): (.+)$/', $line, $m)) {
                $frames[] = ['at' => \sprintf('%s:%s', $this->relative($m[1]), $m[2]), 'call' => $m[3], 'app' => $this->isApp($m[1])];
            }
        }

        return $frames;
    }

    /**
     * @param list<array{at: string, call: string|null, app: bool}> $frames
     */
    private function failedIn(array $frames): ?string
    {
        foreach ($frames as $frame) {
            if ($frame['app']) {
                return $frame['at'];
            }
        }

        return $frames[0]['at'] ?? null;
    }

    /**
     * @param list<array{at: string, call: string|null, app: bool}> $frames
     *
     * @return list<string>
     */
    private function trace(array $frames): array
    {
        return array_map(fn (array $f): string => ($f['app'] ? '' : '[vendor] ').$f['at'].(null === $f['call'] ? '' : ' '.$this->trim($f['call'], self::MAX_CALL_LENGTH)), \array_slice($frames, 0, self::MAX_FRAMES));
    }

    /**
     * @param list<FailedMessage> $messages
     */
    private function last(array $messages): float
    {
        $dates = array_filter(array_map(static fn (FailedMessage $m): ?float => null === $m->lastFailedAt ? null : (float) $m->lastFailedAt->format('U.u'), $messages));

        return [] === $dates ? 0.0 : max($dates);
    }

    private function isApp(string $file): bool
    {
        return !str_contains($file, '/vendor/') && !str_starts_with($file, '[internal');
    }

    private function relative(string $file): string
    {
        foreach ($this->projectDirs as $dir) {
            if (str_starts_with($file, rtrim($dir, '/').'/')) {
                return substr($file, \strlen(rtrim($dir, '/')) + 1);
            }
        }

        return $file;
    }

    private function trim(string $text, int $length): string
    {
        return mb_strimwidth($text, 0, $length, '…');
    }
}
