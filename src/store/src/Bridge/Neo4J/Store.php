<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Neo4J;

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
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $endpointUrl,
        #[\SensitiveParameter] private string $username,
        #[\SensitiveParameter] private string $password,
        #[\SensitiveParameter] private string $databaseName,
        private string $vectorIndexName,
        private string $nodeName,
        private string $embeddingsField = 'embeddings',
        private int $embeddingsDimension = 1536,
        private string $embeddingsDistance = 'cosine',
        private bool $quantization = false,
    ) {
    }

    public function add(VectorDocument ...$documents): void
    {
        foreach ($documents as $document) {
            $this->request('POST', \sprintf('db/%s/query/v2', $this->databaseName), [
                'statement' => \sprintf('CREATE (n:%s {id: $id, metadata: $metadata, %s: $embeddings}) RETURN n', $this->nodeName, $this->embeddingsField),
                'parameters' => [
                    'id' => $document->id->toRfc4122(),
                    'metadata' => json_encode($document->metadata->getArrayCopy()),
                    'embeddings' => $document->vector->getData(),
                ],
            ]);
        }
    }

    public function query(Vector $vector, array $options = [], ?float $minScore = null): array
    {
        $response = $this->request('POST', \sprintf('db/%s/query/v2', $this->databaseName), [
            'statement' => \sprintf('CALL db.index.vector.queryNodes("%s", 5, $vectors) YIELD node, score RETURN node, score', $this->vectorIndexName),
            'parameters' => [
                'vectors' => $vector->getData(),
            ],
        ]);

        return array_map($this->convertToVectorDocument(...), $response['data']['values']);
    }

    public function initialize(array $options = []): void
    {
        $this->request('POST', \sprintf('db/%s/query/v2', $this->databaseName), [
            'statement' => \sprintf(
                'CREATE VECTOR INDEX %s IF NOT EXISTS FOR (n:%s) ON n.%s OPTIONS { indexConfig: {`vector.dimensions`: %d, `vector.similarity_function`: "%s", `vector.quantization.enabled`: %s}}',
                $this->vectorIndexName, $this->nodeName, $this->embeddingsField, $this->embeddingsDimension, $this->embeddingsDistance, $this->quantization ? 'true' : 'false',
            ),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $endpoint, array $payload = []): array
    {
        $url = \sprintf('%s/%s', $this->endpointUrl, $endpoint);

        $response = $this->httpClient->request($method, $url, [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => \sprintf('Basic %s', base64_encode(\sprintf('%s:%s', $this->username, $this->password))),
            ],
            'json' => $payload,
        ]);

        return $response->toArray();
    }

    /**
     * @param array<string|int, mixed> $data
     */
    private function convertToVectorDocument(array $data): VectorDocument
    {
        $payload = $data[0];

        $id = $payload['properties']['id'] ?? throw new InvalidArgumentException('Missing "id" field in the document data');

        $vector = !\array_key_exists($this->embeddingsField, $payload['properties']) || null === $payload['properties'][$this->embeddingsField]
            ? new NullVector()
            : new Vector($payload['properties'][$this->embeddingsField]);

        return new VectorDocument(
            id: Uuid::fromString($id),
            vector: $vector,
            metadata: new Metadata(json_decode($payload['properties']['metadata'], true)),
            score: $data[1] ?? null
        );
    }
}
