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

use Symfony\AI\Mate\Bridge\Symfony\Exception\UndecodableMessageException;

/**
 * Reads the failure details of a stored envelope without instantiating any class (PhpSerializer or Symfony Serializer JSON).
 *
 * @internal
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class EnvelopeDecoder
{
    private const ENVELOPE = 'Symfony\Component\Messenger\Envelope';
    private const ERROR_DETAILS = 'Symfony\Component\Messenger\Stamp\ErrorDetailsStamp';
    private const REDELIVERY = 'Symfony\Component\Messenger\Stamp\RedeliveryStamp';
    private const SENT_TO_FAILURE = 'Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp';
    private const STAMP_HEADER_PREFIX = 'X-Message-Stamp-';
    // An envelope is shallow: envelope, stamps, stamp, FlattenException and its previous chain.
    private const MAX_DEPTH = 64;
    private const MAX_TRACE_LINES = 64;

    /**
     * @param array<mixed> $headers
     *
     * @throws UndecodableMessageException
     */
    public function decode(string $id, string $body, array $headers, ?\DateTimeImmutable $storedAt): FailedMessage
    {
        if (isset($headers['type']) && \is_string($headers['type']) && '' !== $headers['type']) {
            return $this->fromHeaders($id, $headers['type'], $headers, $storedAt);
        }

        return $this->fromPhpSerialized($id, $body, $storedAt);
    }

    /**
     * @param array<mixed> $headers
     */
    private function fromHeaders(string $id, string $messageClass, array $headers, ?\DateTimeImmutable $storedAt): FailedMessage
    {
        $stamps = [];
        foreach ($headers as $name => $value) {
            if (!\is_string($name) || !str_starts_with($name, self::STAMP_HEADER_PREFIX) || !\is_string($value)) {
                continue;
            }
            $decoded = json_decode($value, true);
            if (!\is_array($decoded)) {
                throw new UndecodableMessageException(\sprintf('Header "%s" is not JSON; a serializer other than PhpSerializer or the JSON Symfony Serializer wrote this message.', $name));
            }
            $stamps[substr($name, \strlen(self::STAMP_HEADER_PREFIX))] = array_values(array_filter($decoded, \is_array(...)));
        }

        $error = $this->last($stamps, self::ERROR_DETAILS);
        $flat = \is_array($error['flattenException'] ?? null) ? $error['flattenException'] : [];

        return $this->message(
            $id,
            $messageClass,
            $error['exceptionClass'] ?? null,
            $error['exceptionMessage'] ?? null,
            $flat['file'] ?? null,
            $flat['line'] ?? null,
            $flat['trace_as_string'] ?? $flat['traceAsString'] ?? '',
            array_map(static fn (array $stamp): array => ['retryCount' => $stamp['retryCount'] ?? null, 'at' => $stamp['redeliveredAt'] ?? null], $stamps[self::REDELIVERY] ?? []),
            $this->last($stamps, self::SENT_TO_FAILURE)['originalReceiverName'] ?? null,
            $storedAt,
        );
    }

    private function fromPhpSerialized(string $id, string $body, ?\DateTimeImmutable $storedAt): FailedMessage
    {
        // Reverses PhpSerializer::encode(): optional base64, always addslashes().
        if (!str_ends_with($body, '}')) {
            $body = base64_decode($body, true);
            if (false === $body) {
                throw new UndecodableMessageException('The body is neither a serialized envelope nor base64; a custom serializer may have written it.');
            }
        }
        $body = stripslashes($body);
        if (1 !== preg_match('/^O:\d+:"'.preg_quote(self::ENVELOPE, '/').'":/', $body)) {
            throw new UndecodableMessageException('The body is not a PHP-serialized Messenger envelope and carries no "type" header; a custom serializer may have written it.');
        }

        $warning = null;
        $body = $this->enumsToObjects($body);
        set_error_handler(static function (int $level, string $message) use (&$warning): bool {
            $warning = $message;

            return true;
        });
        $guard = static function (string $class): never {
            throw new UndecodableMessageException(\sprintf('The message contains an enum ("%s") that is not loadable here.', $class));
        };
        spl_autoload_register($guard, true, true);
        try {
            // No class is allowed or loaded, so no code of a stored payload runs.
            $envelope = unserialize($body, ['allowed_classes' => false, 'max_depth' => self::MAX_DEPTH]);
        } finally {
            spl_autoload_unregister($guard);
            restore_error_handler();
        }
        if (!$envelope instanceof \__PHP_Incomplete_Class) {
            throw new UndecodableMessageException(\sprintf('The serialized envelope is corrupt%s.', null === $warning ? '' : ': '.$warning));
        }

        $properties = $this->properties($envelope);
        $message = $properties['message'] ?? null;
        $messageClass = match (true) {
            $message instanceof \__PHP_Incomplete_Class => $this->className($message),
            \is_object($message) => $message::class,
            default => 'unknown',
        };

        $stamps = [];
        foreach (\is_array($properties['stamps'] ?? null) ? $properties['stamps'] : [] as $class => $list) {
            $stamps[(string) $class] = array_map($this->properties(...), \is_array($list) ? array_values($list) : []);
        }

        $error = $this->last($stamps, self::ERROR_DETAILS);
        $flat = $this->properties($error['flattenException'] ?? null);

        return $this->message(
            $id,
            $messageClass,
            $error['exceptionClass'] ?? null,
            $error['exceptionMessage'] ?? null,
            $flat['file'] ?? null,
            $flat['line'] ?? null,
            $flat['traceAsString'] ?? '',
            array_map(fn (array $stamp): array => ['retryCount' => $stamp['retryCount'] ?? null, 'at' => $this->date($stamp['redeliveredAt'] ?? null)], $stamps[self::REDELIVERY] ?? []),
            $this->last($stamps, self::SENT_TO_FAILURE)['originalReceiverName'] ?? null,
            $storedAt,
        );
    }

    /**
     * Rewrites enum case tokens into empty objects, which unserialize() turns into incomplete classes, keeping one value slot.
     */
    private function enumsToObjects(string $serialized): string
    {
        $out = '';
        $length = \strlen($serialized);
        $copied = 0;
        for ($i = 0; $i < $length - 1; ++$i) {
            $type = $serialized[$i];
            if (('s' !== $type && 'E' !== $type) || ':' !== $serialized[$i + 1]) {
                continue;
            }
            $colon = strpos($serialized, ':', $i + 2);
            $digits = false === $colon ? '' : substr($serialized, $i + 2, $colon - $i - 2);
            if (false === $colon || !ctype_digit($digits)) {
                continue;
            }
            $end = $colon + (int) $digits + 3;
            if ('E' === $type) {
                $class = strstr(substr($serialized, $colon + 2, (int) $digits), ':', true) ?: 'Enum';
                $out .= substr($serialized, $copied, $i - $copied).\sprintf('O:%d:"%s":0:{}', \strlen($class), $class);
                $copied = $end + 1;
            }
            // Skip `:"<content>";`; the loop's ++$i then lands on the next token.
            $i = $end;
        }

        return $out.substr($serialized, $copied);
    }

    /**
     * @param list<array{retryCount: mixed, at: mixed}> $redeliveries
     */
    private function message(string $id, string $messageClass, mixed $exceptionClass, mixed $exceptionMessage, mixed $file, mixed $line, mixed $trace, array $redeliveries, mixed $originalTransport, ?\DateTimeImmutable $storedAt): FailedMessage
    {
        $retryCount = 0;
        $first = null;
        foreach ($redeliveries as $redelivery) {
            if (\is_int($redelivery['retryCount'])) {
                $retryCount = max($retryCount, $redelivery['retryCount']);
            }
            $at = $redelivery['at'] instanceof \DateTimeImmutable ? $redelivery['at'] : $this->date($redelivery['at']);
            if (null !== $at && (null === $first || $at < $first)) {
                $first = $at;
            }
        }

        return new FailedMessage(
            $id,
            $messageClass,
            \is_string($exceptionClass) ? $exceptionClass : null,
            \is_string($exceptionMessage) ? $exceptionMessage : null,
            \is_string($file) ? $file : null,
            \is_int($line) ? $line : null,
            // Kept small: only the top frames are ever shown, and thousands of messages are held at once.
            \is_string($trace) ? implode("\n", \array_slice(explode("\n", $trace, self::MAX_TRACE_LINES + 1), 0, self::MAX_TRACE_LINES)) : '',
            $retryCount,
            $first ?? $storedAt,
            $storedAt,
            \is_string($originalTransport) ? $originalTransport : null,
        );
    }

    /**
     * @param array<string, list<mixed>> $stamps
     *
     * @return array<string, mixed>
     */
    private function last(array $stamps, string $class): array
    {
        $list = $stamps[$class] ?? [];
        $last = end($list);

        return \is_array($last) ? $last : [];
    }

    /**
     * The properties of an object with the visibility prefixes ("\0Class\0", "\0*\0") removed.
     *
     * @return array<string, mixed>
     */
    private function properties(mixed $object): array
    {
        if (!\is_object($object)) {
            return [];
        }

        $properties = [];
        foreach ((array) $object as $key => $value) {
            $key = (string) $key;
            $properties[false === ($pos = strrpos($key, "\0")) ? $key : substr($key, $pos + 1)] = $value;
        }
        unset($properties['__PHP_Incomplete_Class_Name']);

        return $properties;
    }

    private function className(\__PHP_Incomplete_Class $object): string
    {
        $class = ((array) $object)['__PHP_Incomplete_Class_Name'] ?? null;

        return \is_string($class) ? $class : 'unknown';
    }

    /**
     * A date from an incomplete DateTimeImmutable (its "date" and "timezone" properties) or from a
     * normalized date string.
     */
    private function date(mixed $value): ?\DateTimeImmutable
    {
        try {
            if (\is_string($value)) {
                return new \DateTimeImmutable($value);
            }
            $properties = $value instanceof \__PHP_Incomplete_Class ? $this->properties($value) : [];
            if (\is_string($properties['date'] ?? null)) {
                return new \DateTimeImmutable($properties['date'], new \DateTimeZone(\is_string($properties['timezone'] ?? null) ? $properties['timezone'] : 'UTC'));
            }
        } catch (\Exception) {
        }

        return null;
    }
}
