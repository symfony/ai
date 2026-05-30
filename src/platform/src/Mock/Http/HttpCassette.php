<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Mock\Http;

use Symfony\AI\Platform\Exception\RuntimeException;

/**
 * A JSON file of recorded HTTP interactions used by {@see CassetteHttpClient}.
 *
 * Interactions are replayed first-in-first-out, mirroring the drop-in semantics of
 * Symfony's MockHttpClient when given an array of responses. Secrets are redacted on write.
 *
 * @phpstan-type RecordedResponse array{status: int, headers: array<string, list<string>>, body: mixed}
 * @phpstan-type Interaction array{request: array<string, mixed>, response: RecordedResponse}
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class HttpCassette
{
    private const SENSITIVE_HEADERS = ['authorization', 'x-api-key', 'api-key'];

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
     * @param array<string, mixed>        $options the Symfony HttpClient request options
     * @param array<string, list<string>> $headers the recorded response headers
     */
    public function record(string $method, string $url, array $options, int $status, array $headers, string $body): void
    {
        $this->load();

        $this->interactions[] = [
            'request' => self::redactRequest($method, $url, $options),
            'response' => ['status' => $status, 'headers' => $headers, 'body' => $body],
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
            throw new RuntimeException(\sprintf('Cassette "%s" is exhausted after %d interaction(s); re-record it with AI_RECORD=1.', $this->path, \count($this->interactions)));
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
        $headers = [];
        foreach ($options['headers'] ?? [] as $name => $value) {
            if (\in_array(strtolower((string) $name), self::SENSITIVE_HEADERS, true)) {
                continue;
            }

            $headers[$name] = $value;
        }

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
