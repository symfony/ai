<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\ChromaDb;

use Codewithkyrian\ChromaDB\Client;
use Codewithkyrian\ChromaDB\Exceptions\ChromaException;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\AI\Store\Exception\UnsupportedQueryTypeException;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\Query\QueryInterface;
use Symfony\AI\Store\Query\TextQuery;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\AI\Store\StoreInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Store implements ManagedStoreInterface, StoreInterface
{
    public function __construct(
        private readonly Client $client,
        private readonly string $collectionName,
    ) {
    }

    public function setup(array $options = []): void
    {
        try {
            $this->client->createCollection($this->collectionName);
        } catch (ChromaException $e) {
            throw new RuntimeException(\sprintf('Could not create collection "%s".', $this->collectionName), previous: $e);
        }
    }

    public function drop(array $options = []): void
    {
        try {
            $this->client->deleteCollection($this->collectionName);
        } catch (ChromaException $e) {
            throw new RuntimeException(\sprintf('Could not delete collection "%s".', $this->collectionName), previous: $e);
        }
    }

    public function add(VectorDocument|array $documents): void
    {
        if ($documents instanceof VectorDocument) {
            $documents = [$documents];
        }

        $ids = [];
        $vectors = [];
        $metadata = [];
        $originalDocuments = [];
        foreach ($documents as $document) {
            $ids[] = (string) $document->getId();
            $vectors[] = $document->getVector()->getData();
            $metadataCopy = $document->getMetadata()->getArrayCopy();
            $originalDocuments[] = $document->getMetadata()->getText() ?? '';
            unset($metadataCopy[Metadata::KEY_TEXT]);
            $metadata[] = $metadataCopy;
        }

        $collection = $this->client->getOrCreateCollection($this->collectionName);

        // @phpstan-ignore argument.type (chromadb-php library has incorrect PHPDoc type for $metadatas parameter)
        $collection->add($ids, $vectors, $metadata, $originalDocuments);
    }

    public function remove(string|array $ids, array $options = []): void
    {
        if (\is_string($ids)) {
            $ids = [$ids];
        }

        if ([] === $ids) {
            return;
        }

        $collection = $this->client->getOrCreateCollection($this->collectionName);
        $collection->delete(ids: $ids);
    }

    public function supports(string $queryClass): bool
    {
        return \in_array($queryClass, [
            VectorQuery::class,
            TextQuery::class,
        ], true);
    }

    /**
     * @param array{where?: array<string, string>, whereDocument?: array<string, mixed>, include?: array<string>, limit?: positive-int} $options
     */
    public function query(QueryInterface $query, array $options = []): iterable
    {
        if (!$this->supports($query::class)) {
            throw new UnsupportedQueryTypeException($query::class, $this);
        }

        return match (true) {
            $query instanceof VectorQuery => $this->queryVector($query, $options),
            $query instanceof TextQuery => $this->queryText($query, $options),
            default => throw new UnsupportedQueryTypeException($query::class, $this),
        };
    }

    /**
     * @param array{where?: array<string, string>, whereDocument?: array<string, mixed>, include?: array<string>, limit?: positive-int} $options
     *
     * @return iterable<VectorDocument>
     */
    private function queryVector(VectorQuery $query, array $options): iterable
    {
        $include = $this->buildInclude($options);

        $collection = $this->client->getOrCreateCollection($this->collectionName);
        $queryResponse = $collection->query(
            queryEmbeddings: [$query->getVector()->getData()],
            nResults: $options['limit'] ?? 4,
            where: $options['where'] ?? null,
            whereDocument: $options['whereDocument'] ?? null,
            include: $include,
        );

        yield from $this->transformResponse($queryResponse);
    }

    /**
     * @param array{where?: array<string, string>, whereDocument?: array<string, mixed>, include?: array<string>, limit?: positive-int} $options
     *
     * @return iterable<VectorDocument>
     */
    private function queryText(TextQuery $query, array $options): iterable
    {
        $include = $this->buildInclude($options);

        $collection = $this->client->getOrCreateCollection($this->collectionName);
        $queryResponse = $collection->query(
            queryTexts: $query->getTexts(),
            nResults: $options['limit'] ?? 4,
            where: $options['where'] ?? null,
            whereDocument: $options['whereDocument'] ?? null,
            include: $include,
        );

        yield from $this->transformResponse($queryResponse);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string>|null
     */
    private function buildInclude(array $options): ?array
    {
        if ([] === ($options['include'] ?? [])) {
            return null;
        }

        return array_values(
            array_unique(
                array_merge(['embeddings', 'metadatas', 'distances'], $options['include'])
            )
        );
    }

    /**
     * @return iterable<VectorDocument>
     */
    private function transformResponse(object $queryResponse): iterable
    {
        $metaCount = \count($queryResponse->metadatas[0]);

        for ($i = 0; $i < $metaCount; ++$i) {
            $metaData = new Metadata($queryResponse->metadatas[0][$i]);
            if (isset($queryResponse->documents[0][$i])) {
                $metaData->setText($queryResponse->documents[0][$i]);
            }

            yield new VectorDocument(
                id: $queryResponse->ids[0][$i],
                vector: new Vector($queryResponse->embeddings[0][$i]),
                metadata: $metaData,
                score: $queryResponse->distances[0][$i] ?? null,
            );
        }
    }
}
