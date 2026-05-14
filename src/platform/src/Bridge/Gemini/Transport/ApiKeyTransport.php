<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Transport;

use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TransportInterface;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Google AI Studio (`generativelanguage.googleapis.com`) transport.
 *
 * Sends the API key via the `x-goog-api-key` header (the documented method
 * for direct Gemini API access). HTTP-level errors (401/400/429) become
 * typed exceptions; the handler stays free of protocol concerns.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ApiKeyTransport implements TransportInterface
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/';

    private readonly EventSourceHttpClient $httpClient;

    public function __construct(
        HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
    ) {
        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
    }

    public function send(Model $model, RequestEnvelope $request, array $options = []): RawResultInterface
    {
        $headers = array_merge(
            ['x-goog-api-key' => $this->apiKey],
            $request->getHeaders(),
        );

        $response = $this->httpClient->request($request->getMethod(), self::BASE_URL.ltrim($request->getPath(), '/'), [
            'headers' => $headers,
            'json' => $request->getPayload(),
        ]);

        $status = $response->getStatusCode();

        if (401 === $status) {
            throw new AuthenticationException(self::extractErrorMessage($response) ?? 'Unauthorized');
        }

        if (400 === $status) {
            throw new BadRequestException(self::extractErrorMessage($response) ?? 'Bad Request');
        }

        if (429 === $status) {
            $retryAfter = $response->getHeaders(false)['retry-after'][0] ?? null;
            throw new RateLimitExceededException(null !== $retryAfter && ctype_digit($retryAfter) ? (int) $retryAfter : null);
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

        return $decoded['error']['message'] ?? null;
    }
}
