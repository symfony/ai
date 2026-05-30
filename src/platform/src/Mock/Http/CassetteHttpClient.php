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

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * An {@see HttpClientInterface} that records real HTTP responses to an {@see HttpCassette} and
 * replays them offline. Pass it to any bridge `Factory` (which accepts a `?HttpClientInterface`)
 * so the real Contract, ModelClient and ResultConverter run end-to-end against recorded bytes.
 *
 * By default the mode follows the cassette file (override with the explicit `$record` argument):
 *  - record (cassette missing, + a real client): performs the live request, persists status/headers/body
 *    (secrets redacted), and returns a buffered response identical to what replay will serve;
 *  - replay (cassette exists): serves the next recorded interaction (FIFO).
 *
 * Delete the cassette to re-record. Requires `symfony/http-client` (the `MockHttpClient`/`MockResponse` classes).
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class CassetteHttpClient implements HttpClientInterface
{
    private readonly bool $record;

    private readonly MockHttpClient $replayClient;

    /**
     * @param bool|null $record whether to record (`true`) or replay (`false`); defaults to recording
     *                          when the cassette file does not exist yet and replaying otherwise
     */
    public function __construct(
        private readonly HttpCassette $cassette,
        private readonly ?HttpClientInterface $realClient = null,
        ?bool $record = null,
    ) {
        $this->record = $record ?? !$cassette->exists();

        if ($this->record && null === $realClient) {
            throw new InvalidArgumentException('Recording requires a real HttpClientInterface to delegate to; pass one as the second argument.');
        }

        $this->replayClient = new MockHttpClient(function (): MockResponse {
            return self::toMockResponse($this->cassette->next());
        });
    }

    /**
     * @param array<string, mixed> $options
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        if (!$this->record) {
            return $this->replayClient->request($method, $url, $options);
        }

        $response = $this->realClient->request($method, $url, $options);

        $status = $response->getStatusCode();
        $headers = $response->getHeaders(false);
        $body = $response->getContent(false);

        $this->cassette->record($method, $url, $options, $status, $headers, $body);

        // Re-issue the recorded bytes through a MockHttpClient so the caller reads exactly what
        // replay will serve (a bare MockResponse cannot be consumed on its own).
        return (new MockHttpClient(self::toMockResponse(['status' => $status, 'headers' => $headers, 'body' => $body])))
            ->request($method, $url, $options);
    }

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        return $this->replayClient->stream($responses, $timeout);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function withOptions(array $options): static
    {
        return new self($this->cassette, $this->realClient?->withOptions($options), $this->record);
    }

    /**
     * @param array{status: int, headers: array<string, list<string>>, body: mixed} $recorded
     */
    private static function toMockResponse(array $recorded): MockResponse
    {
        $body = $recorded['body'];
        if (!\is_string($body)) {
            $body = json_encode($body, \JSON_THROW_ON_ERROR);
        }

        return new MockResponse($body, [
            'http_code' => $recorded['status'],
            'response_headers' => self::flattenHeaders($recorded['headers']),
        ]);
    }

    /**
     * @param array<string, list<string>|string> $headers
     *
     * @return list<string>
     */
    private static function flattenHeaders(array $headers): array
    {
        $flat = [];
        foreach ($headers as $name => $values) {
            foreach ((array) $values as $value) {
                $flat[] = $name.': '.$value;
            }
        }

        return $flat;
    }
}
