<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic\Transport;

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
 * Direct Anthropic HTTP transport.
 *
 * Owns api.anthropic.com auth (x-api-key + anthropic-version) and
 * protocol-level error mapping (401 / 400 / 429 → typed exceptions).
 * Contract-level errors (response body's `type: error`) stay in the
 * handler.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class HttpTransport implements TransportInterface
{
    private readonly EventSourceHttpClient $httpClient;

    public function __construct(
        HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly string $baseUrl = 'https://api.anthropic.com',
        private readonly string $anthropicVersion = '2023-06-01',
    ) {
        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
    }

    public function send(Model $model, RequestEnvelope $request, array $options = []): RawResultInterface
    {
        $headers = array_merge(
            [
                'x-api-key' => $this->apiKey,
                'anthropic-version' => $this->anthropicVersion,
            ],
            $request->getHeaders(),
        );

        $response = $this->httpClient->request($request->getMethod(), $this->baseUrl.$request->getPath(), [
            'headers' => $headers,
            'json' => $request->getPayload(),
        ]);

        $statusCode = $response->getStatusCode();

        if (401 === $statusCode) {
            $errorMessage = json_decode($response->getContent(false), true)['error']['message'] ?? 'Unauthorized';
            throw new AuthenticationException($errorMessage);
        }

        if (400 === $statusCode) {
            $errorMessage = json_decode($response->getContent(false), true)['error']['message'] ?? 'Bad Request';
            throw new BadRequestException($errorMessage);
        }

        if (429 === $statusCode) {
            $retryAfter = $response->getHeaders(false)['retry-after'][0] ?? null;
            throw new RateLimitExceededException(null !== $retryAfter ? (int) $retryAfter : null);
        }

        return new RawHttpResult($response);
    }
}
