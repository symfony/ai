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
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class Store implements ManagedStoreInterface, StoreInterface
{
    /**
     * @param string $embedder        The name of the embedder where vectors are stored
     * @param string $vectorFieldName The name of the field in the index that contains the vector
     * @param float  $semanticRatio   The ratio between semantic (vector) and full-text search (0.0 to 1.0)
     *                                - 0.0 = 100% full-text search
     *                                - 0.5 = balanced hybrid search
     *                                - 1.0 = 100% semantic search (vector only)
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $endpointUrl,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly string $indexName,
        private readonly string $embedder = 'default',
        private readonly string $vectorFieldName = '_vectors',
        private readonly int $embeddingsDimension = 1536,
        private readonly float $semanticRatio = 1.0,
    ) {
        if ($semanticRatio < 0.0 || $semanticRatio > 1.0) {
            throw new InvalidArgumentException(\sprintf('The semantic ratio must be between 0.0 and 1.0, "%s" given.', $semanticRatio));
        }
    }

    public function setup(array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options.');
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

    public function add(VectorDocument ...$documents): void
    {
        $this->request('PUT', \sprintf('indexes/%s/documents', $this->indexName), array_map(
            $this->convertToIndexableArray(...), $documents)
        );
    }

    public function query(Vector $vector, array $options = []): iterable
    {
        $semanticRatio = $options['semanticRatio'] ?? $this->semanticRatio;

        if ($semanticRatio < 0.0 || $semanticRatio > 1.0) {
            throw new InvalidArgumentException(\sprintf('The semantic ratio must be between 0.0 and 1.0, "%s" given.', $semanticRatio));
        }

        $result = $this->request('POST', \sprintf('indexes/%s/search', $this->indexName), [
            'q' => $options['q'] ?? '',
            'vector' => $vector->getData(),
            'showRankingScore' => true,
            'retrieveVectors' => true,
            'hybrid' => [
                'embedder' => $this->embedder,
                'semanticRatio' => $semanticRatio,
            ],
        ]);

        foreach ($result['hits'] as $item) {
            yield $this->convertToVectorDocument($item);
        }
    }

    public function drop(array $options = []): void
    {
        $this->request('DELETE', \sprintf('indexes/%s', $this->indexName), []);
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
        $id = $data['id'] ?? throw new InvalidArgumentException('Missing "id" field in the document data.');
        $vector = !\array_key_exists($this->vectorFieldName, $data) || null === $data[$this->vectorFieldName]
            ? new NullVector()
            : new Vector($data[$this->vectorFieldName][$this->embedder]['embeddings']);

        $score = $data['_rankingScore'] ?? null;

        unset($data['id'], $data[$this->vectorFieldName], $data['_rankingScore']);

        return new VectorDocument(Uuid::fromString($id), $vector, new Metadata($data), $score);
    }
}
