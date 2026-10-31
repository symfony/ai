<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Pinecone;

use Symfony\AI\Platform\Vector\NullVector;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Document\VectorDocumentInterface;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\AI\Store\Exception\UnsupportedQueryTypeException;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\Query\QueryInterface;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\AI\Store\StoreInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Store implements ManagedStoreInterface, StoreInterface
{
    private const API_VERSION = '2025-10';

    private ?string $resolvedIndexHost = null;

    /**
     * @param array<string, mixed>                   $filter
     * @param 'cosine'|'euclidean'|'dotproduct'|null $metric
     * @param string|null                            $indexHost       URL of the index, looked up through the control plane when null
     * @param string                                 $controlPlaneUrl URL of the Pinecone control plane
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly string $indexName,
        private readonly ?string $namespace = null,
        private readonly array $filter = [],
        private readonly int $topK = 3,
        private readonly int $dimensions = 1536,
        private readonly ?string $metric = null,
        private readonly ?string $cloud = null,
        private readonly ?string $region = null,
        private readonly ?string $indexHost = null,
        private readonly string $controlPlaneUrl = 'https://api.pinecone.io',
    ) {
    }

    public function setup(array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options.');
        }

        $this->request('POST', $this->controlPlaneUrl().'/indexes', [
            'name' => $this->indexName,
            'dimension' => $this->dimensions,
            'metric' => $this->metric ?? 'cosine',
            'spec' => [
                'serverless' => [
                    'cloud' => $this->cloud ?? 'aws',
                    'region' => $this->region ?? 'us-west-2',
                ],
            ],
        ]);
    }

    public function add(VectorDocumentInterface|array $documents): void
    {
        if ($documents instanceof VectorDocumentInterface) {
            $documents = [$documents];
        }

        $vectors = [];
        foreach ($documents as $document) {
            $vector = [
                'id' => (string) $document->getId(),
                'values' => $document->getVector()->getData(),
            ];

            $metadata = $document->getMetadata()->getArrayCopy();
            if ([] !== $metadata) {
                $vector['metadata'] = $metadata;
            }

            $vectors[] = $vector;
        }

        if ([] === $vectors) {
            return;
        }

        $payload = ['vectors' => $vectors];
        if (null !== $namespace = $this->namespace()) {
            $payload['namespace'] = $namespace;
        }

        $this->request('POST', $this->indexUrl().'/vectors/upsert', $payload);
    }

    /**
     * @param array{
     *     deleteAll?: bool,
     * } $options
     */
    public function remove(string|array $ids, array $options = []): void
    {
        if (\is_string($ids)) {
            $ids = [$ids];
        }

        if ($options['deleteAll'] ?? false) {
            $this->deleteVectors(['deleteAll' => true]);

            return;
        }

        // The API allows a maximum of 1000 ids per request
        foreach (array_chunk($ids, 1000) as $chunkIds) {
            $this->deleteVectors(['ids' => $chunkIds]);
        }
    }

    public function clear(array $options = []): void
    {
        $this->deleteVectors(['deleteAll' => true]);
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

        $payload = [
            'vector' => $query->getVector()->getData(),
            'topK' => $options['topK'] ?? $this->topK,
            'includeMetadata' => $options['includeMetadata'] ?? true,
            'includeValues' => $options['includeValues'] ?? false,
        ];

        $namespace = $options['namespace'] ?? $this->namespace;
        if (null !== $namespace && '' !== $namespace) {
            $payload['namespace'] = $namespace;
        }

        $filter = $options['filter'] ?? $this->filter;
        if ([] !== $filter) {
            $payload['filter'] = $filter;
        }

        $result = $this->request('POST', $this->indexUrl().'/query', $payload);

        foreach ($result['matches'] ?? [] as $match) {
            $vector = isset($match['values']) && [] !== $match['values']
                ? new Vector($match['values'])
                : new NullVector();

            yield new VectorDocument(
                id: $match['id'],
                vector: $vector,
                metadata: new Metadata($match['metadata'] ?? []),
                score: $match['score'],
            );
        }
    }

    public function drop(array $options = []): void
    {
        $this->request('DELETE', $this->controlPlaneUrl().'/indexes/'.rawurlencode($this->indexName));

        $this->resolvedIndexHost = null;
    }

    public function count(): int
    {
        // Pinecone Local rejects the request without a body, and an empty array would be encoded as "[]"
        $stats = $this->request('POST', $this->indexUrl().'/describe_index_stats', new \stdClass());

        if (null !== $namespace = $this->namespace()) {
            return $stats['namespaces'][$namespace]['vectorCount'] ?? 0;
        }

        return $stats['totalVectorCount'] ?? 0;
    }

    /**
     * @param array{ids?: list<string>, deleteAll?: bool} $payload
     */
    private function deleteVectors(array $payload): void
    {
        if (null !== $namespace = $this->namespace()) {
            $payload['namespace'] = $namespace;
        }

        $this->request('POST', $this->indexUrl().'/vectors/delete', $payload);
    }

    private function namespace(): ?string
    {
        if (null === $this->namespace || '' === $this->namespace) {
            return null;
        }

        return $this->namespace;
    }

    private function controlPlaneUrl(): string
    {
        return rtrim($this->controlPlaneUrl, '/');
    }

    private function indexUrl(): string
    {
        if (null !== $this->indexHost) {
            return rtrim($this->indexHost, '/');
        }

        if (null !== $this->resolvedIndexHost) {
            return $this->resolvedIndexHost;
        }

        $index = $this->request('GET', $this->controlPlaneUrl().'/indexes/'.rawurlencode($this->indexName));

        $host = $index['host'] ?? throw new RuntimeException(\sprintf('The host of the Pinecone index "%s" is unknown.', $this->indexName));

        // Pinecone Local reports the host without a scheme, so the one of the control plane is used
        if (!str_contains($host, '://')) {
            $host = (parse_url($this->controlPlaneUrl, \PHP_URL_SCHEME) ?: 'https').'://'.$host;
        }

        return $this->resolvedIndexHost = rtrim($host, '/');
    }

    /**
     * @param array<string, mixed>|\stdClass|null $payload
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $url, array|\stdClass|null $payload = null): array
    {
        $options = [
            'headers' => [
                'Api-Key' => $this->apiKey,
                'X-Pinecone-Api-Version' => self::API_VERSION,
            ],
        ];

        if (null !== $payload) {
            $options['json'] = $payload;
        }

        $content = $this->httpClient->request($method, $url, $options)->getContent();

        // Deleting an index has no body, and Pinecone Local answers deleting vectors with an empty JSON string
        if ('' === $content) {
            return [];
        }

        $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);

        return \is_array($data) ? $data : [];
    }
}
