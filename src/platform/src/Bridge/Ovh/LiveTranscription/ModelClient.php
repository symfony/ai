<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ovh\LiveTranscription;

use Symfony\AI\Platform\Bridge\Ovh\LiveTranscription;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Opens a live transcription session with the "POST /gladia/v2/live" endpoint of OVHcloud AI Endpoints.
 *
 * @see https://oai.endpoints.kepler.ai.cloud.ovh.net/doc/solaria-3/openapi.json
 *
 * @author Loïc Sapone <loic@sapone.fr>
 */
final class ModelClient implements ModelClientInterface
{
    private readonly string $baseUrl;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        string $baseUrl = 'https://oai.endpoints.kepler.ai.cloud.ovh.net',
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function supports(Model $model): bool
    {
        return $model instanceof LiveTranscription;
    }

    /**
     * @param array<string, mixed>   $payload The settings of the session, like its "encoding", "sample_rate" or "language_config"
     * @param array{region?: string} $options
     */
    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('The settings of the session must be an array, but a string was given to "%s".', self::class));
        }

        $requestOptions = [
            'auth_bearer' => $this->apiKey,
            'json' => array_merge(['model' => $model->getName()], $payload),
            // The response does not tell which model opened the session
            'user_data' => $model->getName(),
        ];

        if (isset($options['region'])) {
            $requestOptions['query'] = ['region' => $options['region']];
        }

        return new RawHttpResult($this->httpClient->request('POST', $this->baseUrl.'/gladia/v2/live', $requestOptions));
    }
}
