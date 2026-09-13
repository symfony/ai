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
        'xi-api-key',
        'apikey',
        'openai-organization',
        'openai-project',
        'cookie',
        'set-cookie',
    ];

    /**
     * Body and query parameters that carry credentials, for APIs that do not accept them as a header.
     */
    private const SENSITIVE_PARAMETERS = [
        'api_key',
        'apikey',
        'access_token',
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

    private readonly BodyRedactor $redactor;

    /**
     * @param array<string, string> $replacements values replaced in every recorded request and response, for
     *                                            example a real endpoint or credential mapped to the placeholder
     *                                            a replay run sends instead
     * @param BodyRedactor|null     $redactor     replaces secrets and personal data in recorded request
     *                                            bodies; defaults to the built-in rule set
     */
    public function __construct(
        private readonly string $path,
        private readonly array $replacements = [],
        ?BodyRedactor $redactor = null,
    ) {
        $this->redactor = $redactor ?? new BodyRedactor();
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

        [$url, $options] = $this->replaceInRequest($url, $options);

        $response = [
            'status' => $status,
            'headers' => self::sanitizeHeaders($this->replace($headers)),
            'body_format' => $bodyFormat,
            'body' => $this->replace($body),
        ];

        if ('binary' === $bodyFormat) {
            $response['body'] = null;
            $response['body_size'] = \strlen($body);
        }

        $this->interactions[] = [
            'request' => $this->redactRequest($method, $url, $options),
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
        [$url, $options] = $this->replaceInRequest($url, $options);
        $this->assertRequestSignatureMatches($interaction['request'] ?? null, $method, $url, $options, $this->path, $this->cursor);
        ++$this->cursor;

        return $interaction['response'];
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array{string, array<string, mixed>}
     */
    private function replaceInRequest(string $url, array $options): array
    {
        foreach (['headers', 'query', 'json', 'body'] as $option) {
            if (isset($options[$option])) {
                $options[$option] = $this->replace($options[$option]);
            }
        }

        return [$this->replace($url), $options];
    }

    private function replace(mixed $value): mixed
    {
        if ([] === $this->replacements) {
            return $value;
        }

        if (\is_string($value)) {
            return strtr($value, $this->replacements);
        }

        if (\is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->replace($item);
            }
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function redactRequest(string $method, string $url, array $options): array
    {
        $headers = self::sanitizeHeaders(self::normalizeRequestHeaders($options));

        $request = ['method' => $method, 'url' => $url];

        $query = self::requestQuery($options);

        // Redact before signing: a committed cassette has to stay reproducible from what it
        // actually contains, and hashing the raw body would describe something the file no
        // longer holds. Both signatures are computed from the same redacted body, so a freshly
        // written cassette verifies against itself through either path.
        $body = $this->redactor->redact(self::requestBody($options));

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
     * Returns the request body as it is signed and stored.
     *
     * @param array<string, mixed> $options
     */
    private static function requestBody(array $options): mixed
    {
        $body = $options['json'] ?? $options['body'] ?? null;

        if (!\is_array($body)) {
            return self::stubBinary($body);
        }

        return self::redactParameters($body);
    }

    /**
     * Returns the request query as it is signed.
     *
     * @param array<string, mixed> $options
     */
    private static function requestQuery(array $options): mixed
    {
        $query = $options['query'] ?? null;

        if (!\is_array($query)) {
            return $query;
        }

        return self::redactParameters($query);
    }

    /**
     * Redacts credentials that some APIs expect as a body or query parameter instead of a header,
     * before signing: a replay run sends a placeholder credential that must match the recording.
     *
     * Binary uploads are replaced by a stub: their bytes are no JSON and would bloat the cassette like
     * a binary response. A binary string is stubbed with its size and hash; a stream - the file uploads
     * of the audio bridges - with its file name and size only, since reading it would consume the
     * upload. Either stub is derived the same way on record and replay.
     *
     * @param array<mixed> $parameters
     *
     * @return array<mixed>
     */
    private static function redactParameters(array $parameters): array
    {
        foreach ($parameters as $name => $value) {
            if (\is_string($name) && \in_array(strtolower($name), self::SENSITIVE_PARAMETERS, true)) {
                $parameters[$name] = self::REDACTED;
            } elseif (\is_array($value)) {
                $parameters[$name] = self::redactParameters($value);
            } else {
                $parameters[$name] = self::stubBinary($value);
            }
        }

        return $parameters;
    }

    private static function stubBinary(mixed $value): mixed
    {
        if (\is_string($value) && 1 !== preg_match('//u', $value)) {
            return \sprintf('[binary body, %d bytes, xxh128 %s]', \strlen($value), hash('xxh128', $value));
        }

        if (!\is_resource($value)) {
            return $value;
        }

        $stat = fstat($value);

        return \sprintf('[resource %s, %d bytes]', basename(stream_get_meta_data($value)['uri'] ?? ''), false === $stat ? 0 : $stat['size']);
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
    private function assertRequestSignatureMatches(mixed $recordedRequest, string $method, string $url, array $options, string $path, int $cursor): void
    {
        if (!\is_array($recordedRequest)) {
            return;
        }

        $body = self::requestBody($options);
        if (isset($recordedRequest[self::REQUEST_SIGNATURE_V2]) && \is_string($recordedRequest[self::REQUEST_SIGNATURE_V2])) {
            $query = self::requestQuery($options);

            if ($recordedRequest[self::REQUEST_SIGNATURE_V2] === self::signature($method, $url, $query, $body)) {
                return;
            }

            // A cassette written after body redaction stores the redacted form, so a live request
            // carrying the real value cannot match the raw hash. Retry against the redacted body
            // rather than redacting up front: the first attempt is unchanged, so a cassette that
            // happens to hold credential-shaped text cannot start failing because of this.
            //
            // Verification is therefore exact only on the parts redaction leaves alone. Two bodies
            // that redact to the same form are indistinguishable here - by construction, since the
            // cassette no longer holds what would tell them apart.
            $body = $this->redactor->redact($body);
            if ($recordedRequest[self::REQUEST_SIGNATURE_V2] === self::signature($method, $url, $query, $body)) {
                return;
            }

            // $body is the redacted form from here on, so the message compares like with like: the
            // recorded body is redacted too, and reporting "body differs" for a redaction that did
            // its job would point at the wrong thing.
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
     * Returns null when the value cannot be encoded (NAN, invalid UTF-8). Null
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

        // JSON_THROW_ON_ERROR: without it an unencodable value (NAN, invalid UTF-8) makes
        // json_encode() return false, and the file
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
