<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cohere\Transport;

use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TransportInterface;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Cohere HTTP transport (api.cohere.com, Bearer auth).
 *
 * Routes both JSON-bodied endpoints (chat, embed, rerank) and multipart
 * endpoints (audio transcription) — selection is driven by the envelope's
 * Content-Type header.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class HttpTransport implements TransportInterface
{
    private const BASE_URL = 'https://api.cohere.com';

    private readonly EventSourceHttpClient $httpClient;

    public function __construct(
        HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
    ) {
        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
    }

    public function send(Model $model, RequestEnvelope $request, array $options = []): RawResultInterface
    {
        $headers = $request->getHeaders();
        $contentType = $headers['Content-Type'] ?? $headers['content-type'] ?? 'application/json';

        $httpOptions = [
            'auth_bearer' => $this->apiKey,
            'headers' => $headers,
        ];

        if ('multipart/form-data' === $contentType) {
            unset($httpOptions['headers']['Content-Type'], $httpOptions['headers']['content-type']);
            $httpOptions['body'] = $request->getPayload();
        } else {
            $httpOptions['json'] = $request->getPayload();
        }

        $response = $this->httpClient->request($request->getMethod(), self::BASE_URL.$request->getPath(), $httpOptions);

        $status = $response->getStatusCode();

        if (401 === $status) {
            throw new AuthenticationException(self::extractErrorMessage($response) ?? 'Unauthorized');
        }

        if (400 === $status) {
            throw new BadRequestException(self::extractErrorMessage($response) ?? 'Bad Request');
        }

        if (429 === $status) {
            throw new RateLimitExceededException();
        }

        if (200 !== $status) {
            throw new RuntimeException(\sprintf('Unexpected response code %d: "%s"', $status, $response->getContent(false)));
        }

        return new RawHttpResult($response);
    }

    private static function extractErrorMessage(\Symfony\Contracts\HttpClient\ResponseInterface $response): ?string
    {
        $body = $response->getContent(false);
        $decoded = json_decode($body, true);
        if (!\is_array($decoded)) {
            return null;
        }

        return $decoded['error']['message'] ?? $decoded['message'] ?? null;
    }
}
