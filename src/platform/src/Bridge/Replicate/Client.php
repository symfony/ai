<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Replicate;

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\JsonBodyEncodingTrait;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Talks to the Replicate REST API, one request per call; resolving a prediction is the job of
 * {@see ReplicateJobClient}.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Client
{
    use HttpStatusErrorHandlingTrait;
    use JsonBodyEncodingTrait;

    private readonly string $baseUrl;

    /**
     * @param string $baseUrl Base URL of a Replicate-compatible endpoint, with or without a trailing slash
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        string $baseUrl = 'https://api.replicate.com',
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * @param string               $model The model name on Replicate, e.g. "meta/meta-llama-3.1-405b-instruct"
     * @param array<string, mixed> $body
     */
    public function request(string $model, string $endpoint, array $body): ResponseInterface
    {
        $url = \sprintf('%s/v1/models/%s/%s', $this->baseUrl, $model, $endpoint);

        return $this->httpClient->request('POST', $url, [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $this->apiKey,
            'body' => $this->encodeJsonBody(['input' => $body]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $path): array
    {
        $response = $this->httpClient->request('GET', $this->baseUrl.'/'.$path, [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $this->apiKey,
        ]);

        $this->throwOnHttpError($response);

        // Beyond the statuses the shared handling knows, any other error - an exhausted balance, a
        // refused request - would otherwise read as an unknown state and be polled until the budget runs out.
        if (400 <= $response->getStatusCode()) {
            throw new RuntimeException(\sprintf('Replicate API error (HTTP %d): "%s".', $response->getStatusCode(), $this->extractErrorMessage($response) ?? 'Unknown error'));
        }

        return $response->toArray(false);
    }

    /**
     * Overrides the shared lookup: Replicate puts its message into `detail` rather than `error.message`.
     */
    private function extractErrorMessage(ResponseInterface $response): ?string
    {
        try {
            $data = $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            return null;
        }

        return \is_string($data['detail'] ?? null) ? $data['detail'] : null;
    }
}
