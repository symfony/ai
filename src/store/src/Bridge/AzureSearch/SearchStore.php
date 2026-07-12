<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\AzureSearch;

use Symfony\AI\Platform\Vector\NullVector;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\AI\Store\Exception\UnsupportedQueryTypeException;
use Symfony\AI\Store\Query\QueryInterface;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\AI\Store\StoreInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class SearchStore implements StoreInterface
{
    private const BATCH_SIZE = 1000;
    private const MAX_UNCHANGED_BATCHES = 10;
    private const INDEX_CATCH_UP_DELAY = 200000;

    /**
     * @param string $vectorFieldName The name of the field int the index that contains the vector
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $indexName,
        private readonly string $vectorFieldName = 'vector',
    ) {
    }

    public function add(VectorDocument|array $documents): void
    {
        if ($documents instanceof VectorDocument) {
            $documents = [$documents];
        }

        $this->request('index', [
            'value' => array_map(fn (VectorDocument $document): array => array_merge([
                'id' => $document->getId(),
                $this->vectorFieldName => $document->getVector()->getData(),
            ], $document->getMetadata()->getArrayCopy()), $documents),
        ]);
    }

    public function remove(string|array $ids, array $options = []): void
    {
        if (\is_string($ids)) {
            $ids = [$ids];
        }

        if ([] === $ids) {
            return;
        }

        $documents = array_map(static fn (string $id): array => [
            'id' => $id,
            '@search.action' => 'delete',
        ], $ids);

        $this->request('index', [
            'value' => $documents,
        ]);
    }

    /**
     * @param array{batch_size?: int} $options
     */
    public function clear(array $options = []): void
    {
        // Azure AI Search has no delete-all operation, so the documents are searched and deleted batch by
        // batch until the index reports no documents left. Deletions are not visible immediately, so an
        // already deleted document can show up in the search result again - it is simply deleted a second
        // time. Only a batch that does not change at all is treated as the index not catching up, and the
        // store waits for it instead of spinning on the same ids forever.
        $batchSize = $this->getBatchSize($options);
        $previousIds = null;
        $unchangedBatches = 0;

        while (true) {
            $result = $this->request('search', [
                'search' => '*',
                'select' => 'id',
                'top' => $batchSize,
            ]);

            $ids = array_column($result['value'], 'id');

            if ([] === $ids) {
                return;
            }

            $this->remove($ids);

            if ($ids !== $previousIds) {
                $unchangedBatches = 0;
            } elseif (++$unchangedBatches >= self::MAX_UNCHANGED_BATCHES) {
                throw new RuntimeException(\sprintf('The "%s" index still returns the same %d document(s) after deleting them repeatedly.', $this->indexName, \count($ids)));
            } else {
                usleep(self::INDEX_CATCH_UP_DELAY);
            }

            $previousIds = $ids;
        }
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
        $result = $this->request('search', [
            'vectorQueries' => [
                [
                    'kind' => 'vector',
                    'vector' => $vector->getData(),
                    'exhaustive' => true,
                    'fields' => $this->vectorFieldName,
                    'weight' => 0.5,
                    'k' => 5,
                ],
            ],
        ]);

        foreach ($result['value'] as $item) {
            yield $this->convertToVectorDocument($item);
        }
    }

    /**
     * @param array{batch_size?: int} $options
     */
    private function getBatchSize(array $options): int
    {
        if ([] !== array_diff(array_keys($options), ['batch_size'])) {
            throw new InvalidArgumentException('Only the "batch_size" option is supported.');
        }

        $batchSize = $options['batch_size'] ?? self::BATCH_SIZE;

        if ($batchSize < 1) {
            throw new InvalidArgumentException('The "batch_size" option must be a positive integer.');
        }

        return $batchSize;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function request(string $endpoint, array $payload): array
    {
        $response = $this->httpClient->request('POST', \sprintf('indexes/%s/docs/%s', $this->indexName, $endpoint), [
            'json' => $payload,
        ]);

        if ($response->getStatusCode() >= 400) {
            throw new RuntimeException(\sprintf('Azure Search request failed: "%s"', $response->getContent(false)));
        }

        $result = $response->toArray();

        // indexing actions are answered with 207 and a per-document status, so a failed delete or upload
        // does not show up in the status code but only in the body
        $failures = [];
        foreach ($result['value'] ?? [] as $item) {
            if (\is_array($item) && false === ($item['status'] ?? null)) {
                $failures[] = \sprintf('%s: %s', $item['key'] ?? 'unknown', $item['errorMessage'] ?? 'unknown error');
            }
        }

        if ([] !== $failures) {
            throw new RuntimeException(\sprintf('Azure Search request failed for %d document(s): "%s".', \count($failures), implode('", "', $failures)));
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function convertToVectorDocument(array $data): VectorDocument
    {
        return new VectorDocument(
            id: $data['id'],
            vector: !\array_key_exists($this->vectorFieldName, $data) || null === $data[$this->vectorFieldName]
                ? new NullVector()
                : new Vector($data[$this->vectorFieldName]),
            metadata: new Metadata($data),
        );
    }
}
