<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\VertexAi\Transport;

use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TransportInterface;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Vertex AI (`aiplatform.googleapis.com`) transport.
 *
 * Builds the project-scoped URL when both `location` and `projectId` are
 * provided, otherwise falls back to the global publisher endpoint. Auth
 * goes via the `?key=` query parameter when an API key is supplied;
 * project-scoped deployments rely on Application Default Credentials
 * being attached to the underlying HttpClient.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class VertexAiTransport implements TransportInterface
{
    private readonly EventSourceHttpClient $httpClient;
    private readonly string $baseUrl;

    public function __construct(
        HttpClientInterface $httpClient,
        ?string $location = null,
        ?string $projectId = null,
        #[\SensitiveParameter] private readonly ?string $apiKey = null,
    ) {
        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
        $this->baseUrl = self::resolveBaseUrl($location, $projectId);
    }

    public function send(Model $model, RequestEnvelope $request, array $options = []): RawResultInterface
    {
        $query = [];
        if (null !== $this->apiKey) {
            $query['key'] = $this->apiKey;
        }

        $response = $this->httpClient->request($request->getMethod(), $this->baseUrl.ltrim($request->getPath(), '/'), [
            'headers' => $request->getHeaders(),
            'json' => $request->getPayload(),
            'query' => $query,
        ]);

        if (429 === $response->getStatusCode()) {
            throw new RateLimitExceededException();
        }

        return new RawHttpResult($response);
    }

    private static function resolveBaseUrl(?string $location, ?string $projectId): string
    {
        if (null !== $location && null !== $projectId) {
            return \sprintf('https://aiplatform.googleapis.com/v1/projects/%s/locations/%s/publishers/google/', $projectId, $location);
        }

        return 'https://aiplatform.googleapis.com/v1/publishers/google/';
    }
}
