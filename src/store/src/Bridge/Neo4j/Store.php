<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Neo4j;

use Symfony\AI\Platform\Vector\NullVector;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\UnsupportedQueryTypeException;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\Query\QueryInterface;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\AI\Store\StoreInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class Store implements ManagedStoreInterface, StoreInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $endpointUrl,
        private readonly string $username,
        #[\SensitiveParameter] private readonly string $password,
        private readonly string $databaseName,
        private readonly string $vectorIndexName,
        private readonly string $nodeName,
        private readonly string $embeddingsField = 'embeddings',
        private readonly int $embeddingsDimension = 1536,
        private readonly string $embeddingsDistance = 'cosine',
        private readonly bool $quantization = false,
    ) {
    }

    public function setup(array $options = []): void
    {
        $this->request('POST', \sprintf('db/%s/query/v2', $this->databaseName), [
            'statement' => \sprintf(
                'CREATE VECTOR INDEX %s IF NOT EXISTS FOR (n:%s) ON n.%s OPTIONS { indexConfig: {`vector.dimensions`: %d, `vector.similarity_function`: "%s", `vector.quantization.enabled`: %s}}',
                $this->vectorIndexName, $this->nodeName, $this->embeddingsField, $this->embeddingsDimension, $this->embeddingsDistance, $this->quantization ? 'true' : 'false',
            ),
        ]);
    }

    public function add(VectorDocument|array $documents): void
    {
        if ($documents instanceof VectorDocument) {
            $documents = [$documents];
        }

        foreach ($documents as $document) {
            $this->request('POST', \sprintf('db/%s/query/v2', $this->databaseName), [
                'statement' => \sprintf('CREATE (n:%s {id: $id, metadata: $metadata, %s: $embeddings}) RETURN n', $this->nodeName, $this->embeddingsField),
                'parameters' => [
                    'id' => $document->getId(),
                    'metadata' => json_encode($document->getMetadata()->getArrayCopy()),
                    'embeddings' => $document->getVector()->getData(),
                ],
            ]);
        }
    }

    public function remove(string|array $ids, array $options = []): void
    {
        if (\is_string($ids)) {
            $ids = [$ids];
        }

        if ([] === $ids) {
            return;
        }

        $this->request('POST', \sprintf('db/%s/query/v2', $this->databaseName), [
            'statement' => \sprintf('MATCH (n:%s) WHERE n.id IN $ids DELETE n', $this->nodeName),
            'parameters' => [
                'ids' => $ids,
            ],
        ]);
    }

    public function supports(string $queryClass): bool
    {
        return VectorQuery::class === $queryClass;
    }

    public function query(QueryInterface $query, array $options = []): iterable
    {
        if (!$query instanceof VectorQuery) {
            throw new UnsupportedQueryTypeException($query::class, $this);
        }

        $vector = $query->getVector();
        $response = $this->request('POST', \sprintf('db/%s/query/v2', $this->databaseName), [
            'statement' => \sprintf('CALL db.index.vector.queryNodes("%s", 5, $vectors) YIELD node, score RETURN node, score', $this->vectorIndexName),
            'parameters' => [
                'vectors' => $vector->getData(),
            ],
        ]);

        foreach ($response['data']['values'] as $item) {
            yield $this->convertToVectorDocument($item);
        }
    }

    public function drop(array $options = []): void
    {
        $this->request('POST', \sprintf('db/%s/query/v2', $this->databaseName), [
            'statement' => 'MATCH (n) DETACH DELETE n',
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
            'auth_basic' => \sprintf('%s:%s', $this->username, $this->password),
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

        $id = $payload['properties']['id'] ?? throw new InvalidArgumentException('Missing "id" field in the document data.');

        $vector = !\array_key_exists($this->embeddingsField, $payload['properties']) || null === $payload['properties'][$this->embeddingsField]
            ? new NullVector()
            : new Vector($payload['properties'][$this->embeddingsField]);

        return new VectorDocument(
            id: $id,
            vector: $vector,
            metadata: new Metadata(json_decode($payload['properties']['metadata'], true)),
            score: $data[1] ?? null
        );
    }
}
