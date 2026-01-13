<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\ManticoreSearch;

use Symfony\AI\Platform\Vector\NullVector;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\LogicException;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class Store implements ManagedStoreInterface, StoreInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $host,
        private readonly string $table,
        private readonly string $field = '_vectors',
        private readonly string $type = 'hnsw',
        private readonly string $similarity = 'cosine',
        private readonly int $dimensions = 1536,
        private readonly string $quantization = '8bit',
    ) {
    }

    public function setup(array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options.');
        }

        $this->request('cli', \sprintf(
            "CREATE TABLE %s (uuid TEXT, metadata JSON, %s FLOAT_VECTOR KNN_TYPE='%s' KNN_DIMS='%s' HNSW_SIMILARITY='%s' QUANTIZATION='%s')",
            $this->table, $this->field, $this->type, $this->dimensions, $this->similarity, $this->quantization,
        ));
    }

    public function drop(array $options = []): void
    {
        $this->request('cli', \sprintf('DROP TABLE %s', $this->table));
    }

    /**
     * @throws \Random\RandomException {@see random_int()}
     */
    public function add(VectorDocument|array $documents): void
    {
        if ($documents instanceof VectorDocument) {
            $documents = [$documents];
        }

        $payload = array_map(
            fn (VectorDocument $document): array => [
                'insert' => [
                    'table' => $this->table,
                    'id' => random_int(0, \PHP_INT_MAX),
                    'doc' => [
                        'uuid' => $document->id->toRfc4122(),
                        $this->field => $document->vector->getData(),
                        'metadata' => json_encode($document->metadata->getArrayCopy()),
                    ],
                ],
            ],
            $documents,
        );

        $this->request('bulk', static function () use ($payload) {
            foreach ($payload as $document) {
                yield json_encode($document).\PHP_EOL;
            }
        });
    }

    public function remove(string|array $ids, array $options = []): void
    {
        throw new LogicException('Method not implemented yet.');
    }

    public function query(Vector $vector, array $options = []): iterable
    {
        $documents = $this->request('search', [
            'table' => $this->table,
            'knn' => [
                'field' => $this->field,
                'query' => $vector->getData(),
                'k' => $options['k'] ?? 250,
                'ef' => $options['ef'] ?? 1000,
            ],
        ]);

        foreach ($documents['hits']['hits'] as $item) {
            yield $this->convertToVectorDocument($item);
        }
    }

    /**
     * @param \Closure|array<string, mixed>|string $query
     *
     * @return array<string, mixed>
     */
    private function request(string $endpoint, \Closure|array|string $query): array
    {
        $options = match ($endpoint) {
            'cli' => [
                'body' => $query,
            ],
            'bulk' => [
                'headers' => [
                    'Content-Type' => 'application/x-ndjson',
                ],
                'body' => $query(),
            ],
            'search' => [
                'json' => $query,
            ],
            default => throw new InvalidArgumentException(\sprintf('The endpoint "%s" is not supported', $endpoint)),
        };

        $response = $this->httpClient->request('POST', \sprintf('%s/%s', $this->host, $endpoint), $options);

        return 'cli' === $endpoint ? [
            'result' => $response->getContent(),
        ] : $response->toArray();
    }

    /**
     * @param array{
     *     _id: int,
     *     _score: int,
     *     _knn_dist: float,
     *     _source: array<string, mixed>,
     * } $data
     */
    private function convertToVectorDocument(array $data): VectorDocument
    {
        $payload = $data['_source'];

        if (!\array_key_exists('uuid', $payload)) {
            throw new InvalidArgumentException('Missing "uuid" field in the document data.');
        }

        $vector = !\array_key_exists($this->field, $payload) || null === $payload[$this->field]
            ? new NullVector()
            : new Vector($payload[$this->field]);

        return new VectorDocument(
            id: $payload['uuid'],
            vector: $vector,
            metadata: new Metadata($payload['metadata'] ?? []),
            score: $data['_knn_dist'] ?? null
        );
    }
}
