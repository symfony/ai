<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Typesense;

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
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class Store implements ManagedStoreInterface, StoreInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $collection,
        private readonly string $vectorFieldName = '_vectors',
        private readonly int $embeddingsDimension = 1536,
    ) {
    }

    public function setup(array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options.');
        }

        $this->request('POST', 'collections', [
            'name' => $this->collection,
            'fields' => [
                [
                    'name' => 'id',
                    'type' => 'string',
                ],
                [
                    'name' => $this->vectorFieldName,
                    'type' => 'float[]',
                    'num_dim' => $this->embeddingsDimension,
                ],
                [
                    'name' => 'metadata',
                    'type' => 'string',
                ],
            ],
        ]);
    }

    public function add(VectorDocument|array $documents): void
    {
        if ($documents instanceof VectorDocument) {
            $documents = [$documents];
        }

        foreach ($documents as $document) {
            $this->request('POST', \sprintf('collections/%s/documents', $this->collection), $this->convertToIndexableArray($document));
        }
    }

    public function remove(string|array $ids, array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options.');
        }

        if (\is_string($ids)) {
            $ids = [$ids];
        }

        if ([] === $ids) {
            return;
        }

        foreach ($ids as $id) {
            if (1 !== preg_match('/^[\w.-]+$/', (string) $id)) {
                throw new InvalidArgumentException(\sprintf('The document id "%s" contains unsupported characters; only letters, digits, "-", "_" and "." are allowed.', $id));
            }
        }

        $this->request('DELETE', \sprintf(
            'collections/%s/documents?filter_by=id:[%s]',
            $this->collection,
            implode(',', $ids),
        ), []);
    }

    public function clear(array $options = []): void
    {
        // "truncate" removes all documents while keeping the collection and its schema in place,
        // in contrast to "filter_by" it does not walk the filter index.
        $this->request('DELETE', \sprintf('collections/%s/documents?truncate=true', $this->collection), []);
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

        $k = $options['k'] ?? 10;
        if (!\is_int($k)) {
            throw new InvalidArgumentException('The "k" option must be an integer.');
        }

        $documents = $this->request('POST', 'multi_search', [
            'searches' => [
                [
                    'collection' => $this->collection,
                    'q' => '*',
                    'vector_query' => \sprintf('%s:([%s], k:%d)', $this->vectorFieldName, implode(', ', $vector->getData()), $k),
                ],
            ],
        ]);

        $results = $documents['results'] ?? null;
        if (!\is_array($results)) {
            throw new RuntimeException('The Typesense search response is malformed.');
        }

        $firstResult = $results[0] ?? null;
        if (!\is_array($firstResult)) {
            throw new RuntimeException('The Typesense search response does not contain a result set.');
        }

        $hits = $firstResult['hits'] ?? null;
        if (!\is_array($hits)) {
            throw new RuntimeException('The Typesense search response does not contain hits.');
        }

        foreach ($hits as $item) {
            if (!\is_array($item)) {
                throw new RuntimeException('The Typesense search response contains an invalid hit.');
            }

            yield $this->convertToVectorDocument($item);
        }
    }

    public function drop(array $options = []): void
    {
        $this->request('DELETE', \sprintf('collections/%s', $this->collection), []);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<mixed>
     */
    private function request(string $method, string $endpoint, array $payload): array
    {
        $result = $this->httpClient->request($method, $endpoint, [
            'json' => [] !== $payload ? $payload : new \stdClass(),
        ]);

        return $result->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function convertToIndexableArray(VectorDocument $document): array
    {
        return [
            'id' => $document->getId(),
            $this->vectorFieldName => $document->getVector()->getData(),
            'metadata' => json_encode($document->getMetadata()->getArrayCopy()),
        ];
    }

    /**
     * @param array<mixed> $data
     */
    private function convertToVectorDocument(array $data): VectorDocument
    {
        $document = $data['document'] ?? throw new InvalidArgumentException('Missing "document" field in the document data.');
        if (!\is_array($document)) {
            throw new InvalidArgumentException('The "document" field must be an array.');
        }

        $id = $document['id'] ?? throw new InvalidArgumentException('Missing "id" field in the document data.');
        if (!\is_string($id) && !\is_int($id)) {
            throw new InvalidArgumentException('The document "id" field must be a string or an integer.');
        }

        $rawVector = $document[$this->vectorFieldName] ?? null;
        if (null === $rawVector) {
            $vector = new NullVector();
        } else {
            if (!\is_array($rawVector)) {
                throw new InvalidArgumentException('The document vector must be an array of numbers.');
            }

            $components = [];
            foreach ($rawVector as $component) {
                if (!\is_int($component) && !\is_float($component)) {
                    throw new InvalidArgumentException('The document vector must contain only numbers.');
                }

                $components[] = (float) $component;
            }

            $vector = new Vector($components);
        }

        $rawMetadata = $document['metadata'] ?? null;
        if (!\is_string($rawMetadata)) {
            throw new InvalidArgumentException('The document metadata must be a JSON encoded string.');
        }

        $metadata = json_decode($rawMetadata, true);
        if (!\is_array($metadata)) {
            throw new InvalidArgumentException('The document metadata is not a valid JSON object.');
        }

        $normalizedMetadata = [];
        foreach ($metadata as $key => $value) {
            if (!\is_string($key)) {
                throw new InvalidArgumentException('The document metadata must be keyed by strings.');
            }

            $normalizedMetadata[$key] = $value;
        }

        $score = $data['vector_distance'] ?? null;
        if (null !== $score && !\is_int($score) && !\is_float($score)) {
            throw new InvalidArgumentException('The document "vector_distance" field must be a number.');
        }

        return new VectorDocument(
            $id,
            $vector,
            new Metadata($normalizedMetadata),
            null === $score ? null : (float) $score,
        );
    }
}
