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
    ) {
    }

    public function add(VectorDocument ...$documents): void
    {
        $response = $this->request('PUT', 'documents', array_map(
            fn (VectorDocument $document): array => [$this, 'convertToIndexableArray'], $documents)
        );

        if (202 !== $response['status']) {
            throw new \RuntimeException(\sprintf('An error occurred while adding documents to Meilisearch: %s', $response['error']));
        }
    }

    public function query(Vector $vector, array $options = [], ?float $minScore = null): array
    {
        $response = $this->request('POST', 'search', [
            'vector' => $vector->getData(),
            'hybrid' => [
                'embedder' => $this->embedder,
            ],
        ]);

        if (200 !== $response['status']) {
            throw new \RuntimeException(\sprintf('An error occurred while querying Meilisearch: %s', $response['error']));
        }

        return array_map([$this, 'convertToVectorDocument'], $response['hits']);
    }

    public function initialize(array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options');
        }

        $indexCreationResponse = $this->httpClient->request('POST', 'indexes', [
            'json' => [
                'uid' => $this->indexName,
                'primaryKey' => 'id',
            ],
        ]);

        if (202 !== $indexCreationResponse->getStatusCode()) {
            throw new \RuntimeException(\sprintf('An error occurred while creating Meilisearch index: %s', $indexCreationResponse->getContent(false)));
        }
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $endpoint, array $payload): array
    {
        $url = \sprintf('%s/indexes/%s/%s', $this->endpointUrl, $this->indexName, $endpoint);
        $response = $this->httpClient->request($method, $url, [
            'headers' => [
                'Authorization' => \sprintf('Bearer %s', $this->apiKey),
            ],
            'json' => $payload,
        ]);

        return $response->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function convertToIndexableArray(VectorDocument $document): array
    {
        return array_merge([
            'id' => $document->id,
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
        return new VectorDocument(
            id: Uuid::fromString($data['id']),
            vector: !\array_key_exists($this->vectorFieldName, $data) || null === $data[$this->vectorFieldName]
                ? new NullVector()
                : new Vector($data[$this->vectorFieldName]),
            metadata: new Metadata($data),
        );
    }
}
