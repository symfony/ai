<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Generic\Transport;

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
 * Generic Bearer-auth HTTP transport for OpenAI-compatible providers.
 *
 * Per-bridge wiring just constructs one of these with the right base URL
 * and API key; nothing else needs to change.
 *
 * Per-provider quirks (for example a non-Bearer auth scheme, custom
 * headers, query-string keys) are not in scope here — bridges that need
 * those still ship their own {@see TransportInterface} implementation.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class HttpTransport implements TransportInterface
{
    private readonly EventSourceHttpClient $httpClient;

    /**
     * @param array<string, string> $extraHeaders Static per-deployment headers (e.g. provider-specific)
     */
    public function __construct(
        HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        #[\SensitiveParameter] private readonly ?string $apiKey = null,
        private readonly array $extraHeaders = [],
    ) {
        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
    }

    public function send(Model $model, RequestEnvelope $request, array $options = []): RawResultInterface
    {
        $headers = array_merge(
            ['Content-Type' => 'application/json'],
            $this->extraHeaders,
            $request->getHeaders(),
        );

        $httpOptions = [
            'headers' => $headers,
            'json' => $request->getPayload(),
        ];

        if (null !== $this->apiKey) {
            $httpOptions['auth_bearer'] = $this->apiKey;
        }

        $response = $this->httpClient->request($request->getMethod(), rtrim($this->baseUrl, '/').$request->getPath(), $httpOptions);

        $status = $response->getStatusCode();

        if (401 === $status) {
            throw new AuthenticationException(self::extractErrorMessage($response) ?? 'Unauthorized');
        }

        if (400 === $status || 404 === $status) {
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

        return $decoded['error']['message'] ?? $decoded['message'] ?? null;
    }
}
