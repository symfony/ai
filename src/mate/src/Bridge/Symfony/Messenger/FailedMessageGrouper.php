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
 * Groups failed messages by exception class, message pattern and first application frame.
 *
 * @phpstan-type Group array{
 *     group_id: string,
 *     count: int,
 *     message_classes: list<string>,
 *     exception_class: string|null,
 *     failed_in: string|null,
 *     sample_messages: list<string>,
 *     trace: list<string>,
 *     retry_count: int|array{min: int, max: int},
 *     first_failed_at: string|null,
 *     last_failed_at: string|null,
 *     original_transports: list<string>,
 *     ids: list<string>,
 *     ids_truncated: bool,
 *     messages?: list<array{id: string, class: string, exception_message: string, retry_count: int, first_failed_at: string|null, last_failed_at: string|null, original_transport: string|null}>,
 *     messages_truncated?: bool,
 * }
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
    public const MAX_GROUPS = 50;
    public const MAX_MESSAGES = 100;

    private const SECRET_KEY = 'password|passwd|pwd|secret|token|api[_-]?key|apikey|authorization|auth|credentials?|signature';

    /**
     * @param list<string> $projectDirs trace paths under these are shown relative
     */
    public function __construct(
        private readonly array $projectDirs = [],
    ) {
    }

    /**
     * Largest group first, groups of equal size by their latest failure. Groups beyond MAX_GROUPS
     * are counted, not listed.
     *
     * @param list<FailedMessage> $messages
     * @param string|null         $only     return only the group with this id, with its messages listed
     *
     * @return array{group_count: int, groups_truncated: bool, omitted_groups: int, omitted_messages: int, groups: list<Group>}
     */
    public function group(array $messages, ?string $only = null): array
    {
        $groups = [];
        foreach ($messages as $message) {
            $frames = $this->frames($message);
            $failedIn = $this->failedIn($frames);
            $key = implode('|', [$message->exceptionClass, $this->pattern((string) $message->exceptionMessage), $failedIn]);
            $groups[$key]['id'] ??= substr(hash('sha256', $key), 0, 8);
            $groups[$key]['frames'] ??= $frames;
            $groups[$key]['failed_in'] ??= $failedIn;
            $groups[$key]['messages'][] = $message;
        }

        $groups = array_values($groups);
        usort($groups, fn (array $a, array $b): int => [\count($b['messages']), $this->last($b['messages'])] <=> [\count($a['messages']), $this->last($a['messages'])]);
        $groupCount = \count($groups);

        $omitted = [];
        if (null !== $only) {
            $groups = array_values(array_filter($groups, static fn (array $g): bool => $g['id'] === $only));
        } else {
            $omitted = \array_slice($groups, self::MAX_GROUPS);
            $groups = \array_slice($groups, 0, self::MAX_GROUPS);
        }

        $result = [];
        foreach ($groups as $group) {
            /** @var list<FailedMessage> $members */
            $members = $group['messages'];
            $retries = array_map(static fn (FailedMessage $m): int => $m->retryCount, $members);
            $first = array_filter(array_map(static fn (FailedMessage $m): ?\DateTimeImmutable => $m->firstFailedAt, $members));
            $last = array_filter(array_map(static fn (FailedMessage $m): ?\DateTimeImmutable => $m->lastFailedAt, $members));
            $ids = array_map(static fn (FailedMessage $m): string => $m->id, $members);
            $idLimit = null === $only ? self::MAX_IDS : self::MAX_MESSAGES;

            $entry = [
                'group_id' => $group['id'],
                'count' => \count($members),
                'message_classes' => array_values(array_unique(array_map(static fn (FailedMessage $m): string => $m->messageClass, $members))),
                'exception_class' => $members[0]->exceptionClass,
                'failed_in' => $group['failed_in'],
                'sample_messages' => \array_slice(array_values(array_unique(array_map(fn (FailedMessage $m): string => $this->sample($m), $members))), 0, self::MAX_SAMPLES),
                'trace' => $this->trace($group['frames']),
                'retry_count' => min($retries) === max($retries) ? min($retries) : ['min' => min($retries), 'max' => max($retries)],
                'first_failed_at' => [] === $first ? null : min($first)->format(\DATE_ATOM),
                'last_failed_at' => [] === $last ? null : max($last)->format(\DATE_ATOM),
                'original_transports' => array_values(array_unique(array_filter(array_map(static fn (FailedMessage $m): ?string => $m->originalTransport, $members)))),
                'ids' => \array_slice($ids, 0, $idLimit),
                'ids_truncated' => \count($ids) > $idLimit,
            ];
            if (null !== $only) {
                $entry['messages'] = array_map(fn (FailedMessage $m): array => [
                    'id' => $m->id,
                    'class' => $m->messageClass,
                    'exception_message' => $this->sample($m),
                    'retry_count' => $m->retryCount,
                    'first_failed_at' => $m->firstFailedAt?->format(\DATE_ATOM),
                    'last_failed_at' => $m->lastFailedAt?->format(\DATE_ATOM),
                    'original_transport' => $m->originalTransport,
                ], \array_slice($members, 0, self::MAX_MESSAGES));
                $entry['messages_truncated'] = \count($members) > self::MAX_MESSAGES;
            }
            $result[] = $entry;
        }

        return [
            'group_count' => $groupCount,
            'groups_truncated' => [] !== $omitted,
            'omitted_groups' => \count($omitted),
            'omitted_messages' => array_sum(array_map(static fn (array $g): int => \count($g['messages']), $omitted)),
            'groups' => $result,
        ];
    }

    /**
     * The message with quoted values, paths and numbers blanked, so `Order 17 not found` and
     * `Order 18 not found` fall into one group. An HTTP status (100 to 599 after "HTTP" or "status")
     * stays: 500 and 404 are different causes.
     */
    public function pattern(string $message): string
    {
        $message = preg_replace('/"[^"]*"|\'[^\']*\'/', '"…"', $message) ?? $message;
        $message = preg_replace('~(/[\w.\-]+)+~', '/…', $message) ?? $message;

        return preg_replace_callback('/(?:HTTP|status(?: code)?)[\s:\/…]{0,6}[1-5]\d\d(?!\d)|\d+(?:[.,]\d+)*/iu', static fn (array $m): string => 1 === preg_match('/^\d/', $m[0]) ? 'N' : $m[0], $message) ?? $message;
    }

    /**
     * URL credentials, bearer tokens, secret-looking key/value pairs and long token-like strings.
     */
    public function mask(string $message): string
    {
        $message = preg_replace('~(://)[^/\s:@]+:[^/\s@]+@~', '$1***@', $message) ?? $message;
        $message = preg_replace('/\b(Bearer|Basic)\s+[\w.~+\/=-]+/i', '$1 ***', $message) ?? $message;
        $message = preg_replace('/([\w-]*(?:'.self::SECRET_KEY.')[\w-]*["\']?\s*[:=]\s*)(?:(?:Bearer|Basic)\s+)?(?:"[^"]*"|\'[^\']*\'|[^\s"\',;&]+)/i', '$1***', $message) ?? $message;

        return preg_replace_callback('/[\w+\/=.-]{24,}/', static fn (array $m): string => str_starts_with($m[0], 'eyJ') || (1 === preg_match('/\d/', $m[0]) && 1 === preg_match('/[A-Za-z]/', $m[0])) ? '***' : $m[0], $message) ?? $message;
    }

    private function sample(FailedMessage $message): string
    {
        return $this->trim($this->mask((string) $message->exceptionMessage), self::MAX_SAMPLE_LENGTH);
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
                $frames[] = ['at' => \sprintf('%s:%s', $this->relative($m[1]), $m[2]), 'call' => $this->withoutArguments($m[3]), 'app' => $this->isApp($m[1])];
            }
        }

        return $frames;
    }

    private function withoutArguments(string $call): string
    {
        $open = strpos($call, '(');

        return false === $open ? $call : substr($call, 0, $open).'()';
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

    /**
     * Outside vendor/, judged below the project directory when the file is in it, so a project
     * checked out under a path containing "vendor" still has application frames.
     */
    private function isApp(string $file): bool
    {
        if (str_starts_with($file, '[internal')) {
            return false;
        }
        $relative = $this->relative($file);

        return $relative === $file ? 1 !== preg_match('~[\\\\/]vendor[\\\\/]~', $file) : 1 !== preg_match('~^vendor[\\\\/]~', $relative);
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
