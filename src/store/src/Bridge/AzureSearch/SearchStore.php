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
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function request(string $endpoint, array $payload): array
    {
        $result = $this->httpClient->request('POST', \sprintf('indexes/%s/docs/%s', $this->indexName, $endpoint), [
            'json' => $payload,
        ]);

        return $result->toArray();
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
