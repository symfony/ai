<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Test\Replay;

use Symfony\AI\Platform\Exception\RuntimeException;

/**
 * A JSON file of recorded HTTP interactions used by {@see CassetteHttpClient}.
 *
 * Interactions are replayed first-in-first-out, mirroring the drop-in semantics of
 * Symfony's MockHttpClient when given an array of responses. On write, secrets are redacted and
 * per-request trace headers are dropped, so a cassette is safe to commit and a re-record diff shows
 * provider changes rather than noise.
 *
 * Streamed responses are stored with their raw Server-Sent Event body and a `sse` body format,
 * so the bridge's stream parser frames them on replay exactly as it would on the wire.
 *
 * Binary response bodies (generated images, audio, ...) are not stored byte-for-byte: committing
 * megabytes of opaque bytes would bloat the repository without making the recording reviewable.
 * The cassette keeps a metadata stub instead - status, headers (including the content type) and
 * the recorded byte size - and {@see CassetteHttpClient} serves a small placeholder body on replay.
 *
 * @phpstan-type RecordedResponse array{status: int, headers: array<string, list<string>>, body: mixed, body_format?: 'json'|'sse'|'binary', body_size?: int}
 * @phpstan-type Interaction array{request: array<string, mixed>, response: RecordedResponse}
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class HttpCassette
{
    /**
     * Request and response headers that must never end up in a committed cassette.
     */
    private const SENSITIVE_HEADERS = [
        'authorization',
        'api-key',
        'x-api-key',
        'x-goog-api-key',
        'x-subscription-token',
        'openai-organization',
        'openai-project',
        'cookie',
        'set-cookie',
    ];

    /**
     * Headers dropped on write: per-request trace identifiers, proxy latencies and timestamps.
     * Nothing asserts on them, they differ on every recording - which would drown a re-record diff
     * in noise, the very signal a cassette exists for - and some tie a recording to an account.
     *
     * Rate limiting headers are deliberately *not* listed: `retry-after` and `x-ratelimit-reset-*`
     * are read by the converters (see `Result\HttpStatusErrorHandlingTrait`), so a cassette must be
     * able to replay them.
     */
    private const TRACE_HEADERS = [
        'date',
        'alt-svc',
        'cf-cache-status',
        'cf-ray',
        'mistral-correlation-id',
        'nel',
        'openai-processing-ms',
        'report-to',
        'request-id',
        'server-timing',
        'x-amzn-requestid',
        'x-amzn-trace-id',
        'x-envoy-upstream-service-time',
        'x-kong-proxy-latency',
        'x-kong-request-id',
        'x-kong-upstream-latency',
        'x-request-id',
    ];

    private const REDACTED = '[redacted]';
    private const REQUEST_SIGNATURE = 'signature';
    private const REQUEST_SIGNATURE_V2 = 'signature_v2';

    /**
     * @var list<Interaction>
     */
    private array $interactions = [];

    private bool $loaded = false;

    private int $cursor = 0;

    public function __construct(
        private readonly string $path,
    ) {
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    /**
     * @param array<string, mixed>        $options    the Symfony HttpClient request options
     * @param array<string, list<string>> $headers    the recorded response headers
     * @param 'json'|'sse'|'binary'       $bodyFormat how the body is framed on the wire
     */
    public function record(string $method, string $url, array $options, int $status, array $headers, string $body, string $bodyFormat = 'json'): void
    {
        $this->load();

        $response = [
            'status' => $status,
            'headers' => self::sanitizeHeaders($headers),
            'body_format' => $bodyFormat,
            'body' => $body,
        ];

        if ('binary' === $bodyFormat) {
            $response['body'] = null;
            $response['body_size'] = \strlen($body);
        }

        $this->interactions[] = [
            'request' => self::redactRequest($method, $url, $options),
            'response' => $response,
        ];

        $this->save();
    }

    /**
     * Returns the next unused recorded response (FIFO).
     *
     * @return RecordedResponse
     */
    public function next(): array
    {
        $interaction = $this->currentInteraction();
        ++$this->cursor;

        return $interaction['response'];
    }

    /**
     * Returns the next unused recorded response after verifying the outgoing request.
     *
     * @param array<string, mixed> $options the Symfony HttpClient request options
     *
     * @return RecordedResponse
     */
    public function nextFor(string $method, string $url, array $options = []): array
    {
        $interaction = $this->currentInteraction();
        self::assertRequestSignatureMatches($interaction['request'] ?? null, $method, $url, $options, $this->path, $this->cursor);
        ++$this->cursor;

        return $interaction['response'];
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private static function redactRequest(string $method, string $url, array $options): array
    {
        $headers = self::sanitizeHeaders(self::normalizeRequestHeaders($options));

        $request = ['method' => $method, 'url' => $url];

        $query = $options['query'] ?? null;
        $body = $options['json'] ?? $options['body'] ?? null;
        $request[self::REQUEST_SIGNATURE] = self::legacySignature($method, $url, $body);
        $request[self::REQUEST_SIGNATURE_V2] = self::signature($method, $url, $query, $body);

        if ([] !== $headers) {
            $request['headers'] = $headers;
        }

        if (null !== $body) {
            $request['body'] = $body;
        }

        return $request;
    }

    /**
     * Normalizes the `headers` option - which may be a map or a list of `Name: value` lines - into a
     * lowercased map, and materializes the `auth_bearer` shorthand so its secret cannot slip through.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, list<string>>
     */
    private static function normalizeRequestHeaders(array $options): array
    {
        $headers = [];

        $rawHeaders = $options['headers'] ?? [];
        if (\is_array($rawHeaders)) {
            foreach ($rawHeaders as $name => $value) {
                if (\is_int($name) && \is_string($value) && str_contains($value, ':')) {
                    [$name, $value] = explode(':', $value, 2);
                    $value = ltrim($value, ' ');
                }

                if (!\is_string($name)) {
                    continue;
                }

                $headers[strtolower($name)] = array_values(array_map(strval(...), (array) $value));
            }
        }

        if (isset($options['auth_bearer'])) {
            $headers['authorization'] = ['Bearer '.self::REDACTED];
        }

        return $headers;
    }

    /**
     * Replaces credentials with a placeholder and drops per-request trace headers.
     *
     * @param array<string, list<string>> $headers
     *
     * @return array<string, list<string>>
     */
    private static function sanitizeHeaders(array $headers): array
    {
        foreach ($headers as $name => $values) {
            $lowercased = strtolower((string) $name);

            if (\in_array($lowercased, self::TRACE_HEADERS, true)) {
                unset($headers[$name]);

                continue;
            }

            if (\in_array($lowercased, self::SENSITIVE_HEADERS, true)) {
                $headers[$name] = [self::REDACTED];
            }
        }

        return $headers;
    }

    private static function signature(string $method, string $url, mixed $query, mixed $body): string
    {
        $normalized = [
            'query' => $query,
            'body' => $body,
        ];

        if (\is_array($normalized['query'])) {
            self::ksortRecursive($normalized['query']);
        }

        if (\is_array($normalized['body'])) {
            self::ksortRecursive($normalized['body']);
        }

        return hash('xxh128', $method.'|'.$url.'|'.json_encode($normalized, \JSON_PRESERVE_ZERO_FRACTION));
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function assertRequestSignatureMatches(mixed $recordedRequest, string $method, string $url, array $options, string $path, int $cursor): void
    {
        if (!\is_array($recordedRequest)) {
            return;
        }

        $body = $options['json'] ?? $options['body'] ?? null;
        if (isset($recordedRequest[self::REQUEST_SIGNATURE_V2]) && \is_string($recordedRequest[self::REQUEST_SIGNATURE_V2])) {
            $signature = self::signature($method, $url, $options['query'] ?? null, $body);
            if ($recordedRequest[self::REQUEST_SIGNATURE_V2] === $signature) {
                return;
            }

            throw new RuntimeException(self::mismatchMessage($recordedRequest, $method, $url, $options, $body, $path, $cursor));
        }

        if (!isset($recordedRequest[self::REQUEST_SIGNATURE]) || !\is_string($recordedRequest[self::REQUEST_SIGNATURE])) {
            return;
        }

        if ($recordedRequest[self::REQUEST_SIGNATURE] === self::legacySignature($method, $url, $body)) {
            return;
        }

        if (self::legacyRecordedRequestMatches($recordedRequest, $method, $url, $body)) {
            return;
        }

        throw new RuntimeException(self::mismatchMessage($recordedRequest, $method, $url, $options, $body, $path, $cursor));
    }

    /**
     * Names the request parts that diverged, so a mismatch is actionable without diffing the
     * cassette JSON by hand. The signature is a hash and cannot say what changed, but `method`,
     * `url` and the `body` are stored alongside it, so they can be compared directly.
     * Values are never interpolated: a cassette body can hold request data. The recorded query
     * is not stored separately, so `query` is only named by elimination, and only when the body
     * could be compared -- otherwise the culprit is unknown and the message stays generic.
     *
     * @param array<string, mixed> $recordedRequest
     * @param array<string, mixed> $options
     */
    private static function mismatchMessage(array $recordedRequest, string $method, string $url, array $options, mixed $body, string $path, int $cursor): string
    {
        $differing = [];

        if (isset($recordedRequest['method']) && \is_string($recordedRequest['method']) && $recordedRequest['method'] !== $method) {
            $differing[] = 'method';
        }

        if (isset($recordedRequest['url']) && \is_string($recordedRequest['url']) && $recordedRequest['url'] !== $url) {
            $differing[] = 'url';
        }

        // Compared through the same encoding the signature uses, so this can never disagree with
        // the hash: `1.0` and `1`, or a raw JSON string and its decoded array, are distinct to
        // `signature()` and must stay distinct here. A looser comparison would clear the body and
        // let the elimination branch below blame the query for a body change.
        // A cassette omits `body` entirely when the recorded request had none, so an absent
        // recorded body is only comparable against an absent live one -- or against a live body,
        // which can only mean the body was added. It is NOT comparable when a recorded body may
        // have been elided on write (a binary stub keeps no body), so that case stays unnamed.
        $recordedBody = $recordedRequest['body'] ?? null;
        $encodedRecorded = self::encodedForComparison($recordedBody);
        $encodedLive = self::encodedForComparison($body);
        $bodyComparable = null !== $encodedRecorded && null !== $encodedLive
            && (null !== $recordedBody || null === $body || !self::bodyMayHaveBeenElided($recordedRequest));
        if ($bodyComparable && $encodedRecorded !== $encodedLive) {
            $differing[] = 'body';
        }

        // The recorded query is folded into the signature rather than stored, so it can only be
        // named by elimination: every comparable part matches, yet the signature does not. This
        // holds only because the body above is compared through the signature's own encoding and
        // `save()` preserves zero fractions; a looser comparison would let a body change hide here
        // and be reported as a query change. Legacy cassettes never signed the query, so they are
        // excluded and fall through to the generic wording.
        if ([] === $differing && $bodyComparable
            && isset($recordedRequest[self::REQUEST_SIGNATURE_V2])
            && \is_string($recordedRequest[self::REQUEST_SIGNATURE_V2])
        ) {
            $differing[] = 'query';
        }

        return \sprintf(
            'Outgoing request #%d does not match the recorded request signature in cassette "%s" (%s); delete it to re-record.',
            $cursor + 1,
            $path,
            [] === $differing ? 'request differs' : implode(', ', array_map(static fn (string $part): string => $part.' differs', $differing)),
        );
    }

    /**
     * Whether a recorded request could have carried a body that was not written to the cassette.
     * `redactRequest()` only stores `body` when one was sent, so its absence normally means there
     * was none -- but a request recorded by an older writer, or one whose body was elided, would
     * look the same. Only the presence of the v2 signature proves the writer stored what it signed.
     *
     * @param array<string, mixed> $recordedRequest
     */
    private static function bodyMayHaveBeenElided(array $recordedRequest): bool
    {
        return !isset($recordedRequest[self::REQUEST_SIGNATURE_V2])
            || !\is_string($recordedRequest[self::REQUEST_SIGNATURE_V2]);
    }

    /**
     * Encodes a body exactly as `signature()` does, so comparing two encodings answers the same
     * question the hash answered. Key order is normalised (it is not a change), but types are
     * not: `JSON_PRESERVE_ZERO_FRACTION` keeps `1.0` distinct from `1`, matching the signature.
     *
     * Returns null when the value cannot be encoded (a resource body, NAN, invalid UTF-8). Null
     * is never equal to another null here by design: an unencodable body proves nothing about
     * what changed, so the caller must treat it as not comparable rather than as a match. A
     * falsy-coalescing fallback would be wrong twice over -- json_encode(0) returns the falsy
     * string "0", which is a perfectly valid encoding.
     */
    private static function encodedForComparison(mixed $body): ?string
    {
        if (\is_array($body)) {
            self::ksortRecursive($body);
        }

        $encoded = json_encode($body, \JSON_PRESERVE_ZERO_FRACTION);

        return false === $encoded ? null : $encoded;
    }

    /**
     * @return Interaction
     */
    private function currentInteraction(): array
    {
        $this->load();

        if (!isset($this->interactions[$this->cursor])) {
            throw new RuntimeException(\sprintf('Cassette "%s" is exhausted after %d interaction(s); delete it to re-record.', $this->path, \count($this->interactions)));
        }

        return $this->interactions[$this->cursor];
    }

    private static function legacySignature(string $method, string $url, mixed $body): string
    {
        $normalized = $body;
        if (\is_array($normalized)) {
            self::ksortRecursive($normalized);
        }

        return hash('xxh128', $method.'|'.$url.'|'.json_encode($normalized));
    }

    /**
     * @param array<string, mixed> $recordedRequest
     */
    private static function legacyRecordedRequestMatches(array $recordedRequest, string $method, string $url, mixed $body): bool
    {
        if (($recordedRequest['method'] ?? null) !== $method || ($recordedRequest['url'] ?? null) !== $url) {
            return false;
        }

        if (!\array_key_exists('body', $recordedRequest)) {
            return null === $body;
        }

        $recordedBody = self::normalizeJsonBody($recordedRequest['body']);
        $requestBody = self::normalizeJsonBody($body);

        if (null === $recordedBody || null === $requestBody) {
            return $recordedRequest['body'] === $body;
        }

        return $recordedBody === $requestBody;
    }

    /**
     * @return array<string|int, mixed>|null
     */
    private static function normalizeJsonBody(mixed $body): ?array
    {
        if (\is_string($body)) {
            $decoded = json_decode($body, true);
            if (!\is_array($decoded)) {
                return null;
            }

            $body = $decoded;
        }

        if (!\is_array($body)) {
            return null;
        }

        self::ksortRecursive($body);

        return $body;
    }

    /**
     * @param array<string|int, mixed> $array
     */
    private static function ksortRecursive(array &$array): void
    {
        ksort($array);
        foreach ($array as &$value) {
            if (\is_array($value)) {
                self::ksortRecursive($value);
            }
        }
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;

        if (!is_file($this->path)) {
            return;
        }

        $raw = file_get_contents($this->path);
        if (false === $raw || '' === trim($raw)) {
            return;
        }

        $data = json_decode($raw, true, flags: \JSON_THROW_ON_ERROR);
        $this->interactions = $data['interactions'] ?? [];
    }

    private function save(): void
    {
        $directory = \dirname($this->path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        // JSON_THROW_ON_ERROR: without it an unencodable value (a `body` opened as a resource by
        // the audio bridges, NAN, invalid UTF-8) makes json_encode() return false, and the file
        // would be overwritten with a bare newline, destroying every interaction recorded so far.
        // JSON_PRESERVE_ZERO_FRACTION: `signature()` hashes with it, so writing without it stores
        // a recorded 1.0 as 1 -- the cassette would no longer reproduce its own signature and the
        // stored body would silently retype the payload.
        // Mirrors `Test/Recording/Cassette.php`.
        try {
            $json = json_encode(['interactions' => $this->interactions], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException(\sprintf('Cannot encode cassette "%s"; the recorded interaction holds a value JSON cannot represent.', $this->path), previous: $exception);
        }

        $contents = $json."\n";
        if (\strlen($contents) !== file_put_contents($this->path, $contents)) {
            throw new RuntimeException(\sprintf('Cannot write cassette "%s".', $this->path));
        }
    }
}
