<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Meilisearch;

use Symfony\AI\Platform\Vector\NullVector;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\InitializableStoreInterface;
use Symfony\AI\Store\VectorStoreInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final readonly class Store implements InitializableStoreInterface, VectorStoreInterface
{
    /**
     * @param string $embedder        The name of the embedder where vectors are stored
     * @param string $vectorFieldName The name of the field int the index that contains the vector
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $endpointUrl,
        #[\SensitiveParameter] private string $apiKey,
        private string $indexName,
        private string $embedder = 'default',
        private string $vectorFieldName = '_vectors',
        private int $embeddingsDimension = 1536,
    ) {
    }

    public function add(VectorDocument ...$documents): void
    {
        $this->request('PUT', \sprintf('indexes/%s/documents', $this->indexName), array_map(
            $this->convertToIndexableArray(...), $documents)
        );
    }

    public function query(Vector $vector, array $options = [], ?float $minScore = null): array
    {
        $result = $this->request('POST', \sprintf('indexes/%s/search', $this->indexName), [
            'vector' => $vector->getData(),
            'showRankingScore' => true,
            'retrieveVectors' => true,
            'hybrid' => [
                'embedder' => $this->embedder,
                'semanticRatio' => 1.0,
            ],
        ]);

        return array_map($this->convertToVectorDocument(...), $result['hits']);
    }

    public function initialize(array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options');
        }

        $this->request('POST', 'indexes', [
            'uid' => $this->indexName,
            'primaryKey' => 'id',
        ]);

        $this->request('PATCH', \sprintf('indexes/%s/settings', $this->indexName), [
            'embedders' => [
                $this->embedder => [
                    'source' => 'userProvided',
                    'dimensions' => $this->embeddingsDimension,
                ],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $endpoint, array $payload): array
    {
        $url = \sprintf('%s/%s', $this->endpointUrl, $endpoint);
        $result = $this->httpClient->request($method, $url, [
            'headers' => [
                'Authorization' => \sprintf('Bearer %s', $this->apiKey),
            ],
            'json' => $payload,
        ]);

        return $result->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function convertToIndexableArray(VectorDocument $document): array
    {
        return array_merge([
            'id' => $document->id->toRfc4122(),
            $this->vectorFieldName => [
                $this->embedder => [
                    'embeddings' => $document->vector->getData(),
                    'regenerate' => false,
                ],
            ],
        ], $document->metadata->getArrayCopy());
    }

    /**
     * @param array<string, mixed> $data
     */
    private function convertToVectorDocument(array $data): VectorDocument
    {
        $id = $data['id'] ?? throw new InvalidArgumentException('Missing "id" field in the document data');
        $vector = !\array_key_exists($this->vectorFieldName, $data) || null === $data[$this->vectorFieldName]
            ? new NullVector() : new Vector($data[$this->vectorFieldName][$this->embedder]['embeddings']);
        $score = $data['_rankingScore'] ?? null;

        unset($data['id'], $data[$this->vectorFieldName], $data['_rankingScore']);

        return new VectorDocument(Uuid::fromString($id), $vector, new Metadata($data), $score);
    }
}
