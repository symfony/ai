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
        'anthropic-workspace',
        'cookie',
        'set-cookie',
        'x-amz-security-token',
        'x-amz-sso_bearer_token',
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
        $this->load();

        if (!isset($this->interactions[$this->cursor])) {
            throw new RuntimeException(\sprintf('Cassette "%s" is exhausted after %d interaction(s); delete it to re-record.', $this->path, \count($this->interactions)));
        }

        return $this->interactions[$this->cursor++]['response'];
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

        $body = $options['json'] ?? $options['body'] ?? null;
        $request['signature'] = self::signature($method, $url, $body);

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

    private static function signature(string $method, string $url, mixed $body): string
    {
        $normalized = $body;
        if (\is_array($normalized)) {
            self::ksortRecursive($normalized);
        }

        return hash('xxh128', $method.'|'.$url.'|'.json_encode($normalized));
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

        file_put_contents($this->path, json_encode(['interactions' => $this->interactions], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE)."\n");
    }
}
