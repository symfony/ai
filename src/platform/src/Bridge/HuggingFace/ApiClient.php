<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace;

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @phpstan-import-type HubModel from ModelMapper
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ApiClient
{
    private const MODELS_URL = 'https://huggingface.co/api/models';
    private const PAGE_SIZE = 1000;

    private readonly HttpClientInterface $httpClient;

    /**
     * @param ?string $apiKey Hugging Face access token, raises the rate limit and grants access to private models
     */
    public function __construct(
        ?HttpClientInterface $httpClient = null,
        #[\SensitiveParameter] private readonly ?string $apiKey = null,
    ) {
        $this->httpClient = $httpClient ?? HttpClient::create();
    }

    /**
     * @return array{
     *     id: string,
     *     downloads: int,
     *     likes: int,
     *     pipeline_tag: string|null,
     *     inference: string|null,
     *     inferenceProviderMapping: array<string, array{
     *         status: 'live'|'staging',
     *         providerId: string,
     *         task: string,
     *         isModelAuthor: bool,
     *     }>|null,
     * }
     */
    public function getModel(string $modelId): array
    {
        $result = $this->request(self::MODELS_URL.'/'.$modelId, [
            'expand' => ['downloads', 'likes', 'pipeline_tag', 'inference', 'inferenceProviderMapping'],
        ]);

        $data = $result->toArray(false);

        if (isset($data['error'])) {
            throw new RuntimeException(\sprintf('Error fetching model info for "%s": "%s"', $modelId, $data['error']));
        }

        return $data;
    }

    /**
     * @param ?string $provider Filter by inference provider (see Provider::*)
     * @param ?string $task     Filter by pipeline tag of the Hub, e.g. "text-classification"
     * @param ?string $search   Search term to filter models by
     * @param bool    $warm     Filter for models with warm inference available
     *
     * @return Model[]
     */
    public function getModels(?string $provider = null, ?string $task = null, ?string $search = null, bool $warm = false): array
    {
        $result = $this->request(self::MODELS_URL, [
            'inference_provider' => $provider,
            'pipeline_tag' => $task,
            'search' => $search,
            ...$warm ? ['inference' => 'warm'] : [],
        ]);

        $data = $result->toArray(false);

        if (isset($data['error'])) {
            throw new RuntimeException(\sprintf('Error fetching models: "%s"', $data['error']));
        }

        return array_map($this->convertToModel(...), $data);
    }

    /**
     * Looks a model up in the model listing, which unlike the model endpoint includes the features of each provider.
     *
     * @return HubModel|null
     */
    public function findModel(string $modelId): ?array
    {
        // a model ID only exceeds one page of matches when it is a generic term, not an actual ID
        foreach ($this->listModels(['search' => $modelId, 'inference_provider' => 'all'], self::PAGE_SIZE) as $model) {
            if ($modelId === $model['id']) {
                return $model;
            }
        }

        return null;
    }

    /**
     * Lists the models served by inference providers, following the pagination of the Hub API.
     *
     * @param array<string, string|int> $filters Query parameters of the Hub API, e.g. "inference_provider", "pipeline_tag" or "sort"
     * @param ?int                      $limit   Maximum number of models to return, all when null
     *
     * @return iterable<HubModel>
     */
    public function listModels(array $filters = [], ?int $limit = null): iterable
    {
        $url = self::MODELS_URL;
        $query = [
            'inference_provider' => 'all',
            ...$filters,
            'limit' => min($limit ?? self::PAGE_SIZE, self::PAGE_SIZE),
            'expand' => ['pipeline_tag', 'inferenceProviderMapping'],
        ];
        $count = 0;

        do {
            $response = $this->request($url, $query);
            $data = $response->toArray(false);

            if (isset($data['error'])) {
                throw new RuntimeException(\sprintf('Error fetching models: "%s"', $data['error']));
            }

            foreach ($data as $model) {
                yield $model;

                if (null !== $limit && ++$count >= $limit) {
                    return;
                }
            }

            $url = $this->getNextPageUrl($response);
            $query = [];
        } while (null !== $url);
    }

    /**
     * @param array<string, mixed> $query
     */
    private function request(string $url, array $query): ResponseInterface
    {
        return $this->httpClient->request('GET', $url, [
            'query' => $query,
            ...null !== $this->apiKey ? ['auth_bearer' => $this->apiKey] : [],
        ]);
    }

    private function getNextPageUrl(ResponseInterface $response): ?string
    {
        $link = $response->getHeaders(false)['link'][0] ?? '';

        if (1 !== preg_match('/<([^>]+)>;\s*rel="next"/', $link, $matches)) {
            return null;
        }

        return $matches[1];
    }

    /**
     * @param array{
     *     id: string,
     *     pipeline_tag?: string,
     * } $data
     */
    private function convertToModel(array $data): Model
    {
        return new Model(
            $data['id'],
            options: [
                'tags' => isset($data['pipeline_tag']) ? [$data['pipeline_tag']] : [],
            ],
        );
    }
}
