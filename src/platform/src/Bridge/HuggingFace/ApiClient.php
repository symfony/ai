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

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ApiClient
{
    public function __construct(
        private ?HttpClientInterface $httpClient = null,
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
        $result = $this->httpClient->request('GET', 'https://huggingface.co/api/models/'.$modelId, [
            'query' => [
                'expand' => ['downloads', 'likes', 'pipeline_tag', 'inference', 'inferenceProviderMapping'],
            ],
        ]);

        $data = $result->toArray(false);

        if (isset($data['error'])) {
            throw new RuntimeException(\sprintf('Error fetching model info for "%s": "%s"', $modelId, $data['error']));
        }

        return $data;
    }

    /**
     * @param ?string $provider Filter by inference provider (see Provider::*)
     * @param ?string $task     Filter by task (see Task::*)
     * @param ?string $search   Search term to filter models by
     * @param bool    $warm     Filter for models with warm inference available
     *
     * @return Model[]
     */
    public function getModels(?string $provider = null, ?string $task = null, ?string $search = null, bool $warm = false): array
    {
        $result = $this->httpClient->request('GET', 'https://huggingface.co/api/models', [
            'query' => [
                'inference_provider' => $provider,
                'pipeline_tag' => $task,
                'search' => $search,
                ...$warm ? ['inference' => 'warm'] : [],
            ],
        ]);

        $data = $result->toArray(false);

        if (isset($data['error'])) {
            throw new RuntimeException(\sprintf('Error fetching models: "%s"', $data['error']));
        }

        return array_map($this->convertToModel(...), $data);
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
