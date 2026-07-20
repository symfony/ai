<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\HelixDb;

use Symfony\AI\Platform\Vector\NullVector;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\AI\Store\Exception\UnsupportedQueryTypeException;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\Query\QueryInterface;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\AI\Store\StoreInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Bridge for HelixDB (https://github.com/HelixDB/helix-db), an open-source graph-vector database.
 *
 * HelixDB does not expose a generic REST API: every query must be authored in HelixQL ".hx"
 * files, compiled and deployed, after which it becomes reachable as a named HTTP endpoint.
 * This store therefore targets a fixed set of named queries that must be deployed beforehand
 * - see the "Resources/schema.hx" and "Resources/queries.hx" files shipped with this bridge.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class Store implements ManagedStoreInterface, StoreInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $endpointUrl,
        private readonly int $embeddingsDimension = 1536,
        private readonly int $defaultTopK = 5,
    ) {
    }

    /**
     * HelixDB schemas are compiled into the deployed ".hx" build and cannot be created at
     * runtime. This method therefore does not create a schema: it verifies that the canonical
     * HelixQL queries shipped with this bridge are deployed and that the instance is reachable.
     */
    public function setup(array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options.');
        }

        try {
            $this->request('searchDocuments', [
                'query_vector' => array_fill(0, max(1, $this->embeddingsDimension), 0.0),
                'k' => 1,
            ]);
        } catch (HttpClientExceptionInterface $exception) {
            throw new RuntimeException('The HelixDB store is not reachable or the canonical HelixQL queries are not deployed. Deploy the bridge\'s "Resources/schema.hx" and "Resources/queries.hx" files before using the store.', previous: $exception);
        }
    }

    public function add(VectorDocument|array $documents): void
    {
        if ($documents instanceof VectorDocument) {
            $documents = [$documents];
        }

        $responses = [];
        foreach ($documents as $document) {
            $responses[] = $this->send('addDocument', [
                'doc_id' => (string) $document->getId(),
                'vector' => $document->getVector()->getData(),
                'metadata' => json_encode($document->getMetadata()->getArrayCopy()),
            ]);
        }

        // Draining the responses awaits the concurrent requests and surfaces HTTP errors.
        foreach ($responses as $response) {
            $response->toArray();
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

        $responses = [];
        foreach ($ids as $id) {
            $responses[] = $this->send('removeDocument', [
                'doc_id' => (string) $id,
            ]);
        }

        // Draining the responses awaits the concurrent requests and surfaces HTTP errors.
        foreach ($responses as $response) {
            $response->toArray();
        }
    }

    public function clear(array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options.');
        }

        // HelixDB cannot drop the compiled schema at runtime, so clearing removes every document
        // through the canonical "dropDocuments" query while leaving the deployed store usable.
        $this->request('dropDocuments', []);
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

        return $this->queryVector($query, $options);
    }

    public function drop(array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options.');
        }

        $this->request('dropDocuments', []);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return iterable<VectorDocument>
     */
    private function queryVector(VectorQuery $query, array $options): iterable
    {
        $limit = $this->defaultTopK;
        if (\array_key_exists('limit', $options) && \is_int($options['limit'])) {
            $limit = $options['limit'];
        }

        $response = $this->request('searchDocuments', [
            'query_vector' => $query->getVector()->getData(),
            'k' => $limit,
        ]);

        $documents = $response['documents'] ?? [];
        if (!\is_array($documents)) {
            return;
        }

        foreach ($documents as $document) {
            if (!\is_array($document)) {
                continue;
            }

            yield $this->convertToVectorDocument($document);
        }
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function request(string $queryName, array $payload): array
    {
        return $this->send($queryName, $payload)->toArray();
    }

    /**
     * Issues a HelixQL query request without awaiting its response, so callers can fire
     * several requests at once and let Symfony HttpClient process them concurrently.
     *
     * @param array<string, mixed> $payload
     */
    private function send(string $queryName, array $payload): ResponseInterface
    {
        return $this->httpClient->request('POST', \sprintf('%s/%s', $this->endpointUrl, $queryName), [
            'json' => $payload,
        ]);
    }

    /**
     * @param array<string|int, mixed> $data
     */
    private function convertToVectorDocument(array $data): VectorDocument
    {
        $id = $data['doc_id'] ?? $data['id'] ?? throw new InvalidArgumentException('Missing "id" field in the document data.');

        $vector = !\array_key_exists('vector', $data) || null === $data['vector']
            ? new NullVector()
            : new Vector($data['vector']);

        $metadata = $data['metadata'] ?? null;
        if (\is_string($metadata)) {
            $metadata = json_decode($metadata, true);
        }
        if (!\is_array($metadata)) {
            $metadata = [];
        }

        return new VectorDocument(
            id: $id,
            vector: $vector,
            metadata: new Metadata($metadata),
            score: $data['score'] ?? $data['distance'] ?? null,
        );
    }
}
