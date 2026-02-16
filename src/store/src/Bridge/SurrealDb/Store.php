<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\SurrealDb;

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
class Store implements ManagedStoreInterface, StoreInterface
{
    private string $authenticationToken = '';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $endpointUrl,
        private readonly string $user,
        #[\SensitiveParameter] private readonly string $password,
        private readonly string $namespace,
        private readonly string $database,
        private readonly string $table = 'vectors',
        private readonly string $vectorFieldName = '_vectors',
        private readonly string $strategy = 'cosine',
        private readonly int $embeddingsDimension = 1536,
        private readonly bool $isNamespacedUser = false,
    ) {
    }

    public function setup(array $options = []): void
    {
        $this->authenticate();

        $this->request('POST', 'sql', \sprintf(
            'DEFINE INDEX %s_vectors ON %s FIELDS %s MTREE DIMENSION %d DIST %s TYPE F32',
            $this->table, $this->table, $this->vectorFieldName, $this->embeddingsDimension, $this->strategy
        ));
    }

    public function add(VectorDocument|array $documents): void
    {
        if ($documents instanceof VectorDocument) {
            $documents = [$documents];
        }

        foreach ($documents as $document) {
            $this->request('POST', \sprintf('key/%s', $this->table), $this->convertToIndexableArray($document));
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

        $recordIds = array_map(fn (string $id) => \sprintf('%s:`%s`', $this->table, $id), $ids);
        $this->request('POST', 'sql', \sprintf('DELETE %s;', implode(', ', $recordIds)));
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
        $vectors = json_encode($vector->getData());

        $results = $this->request('POST', 'sql', \sprintf(
            'SELECT id, %s, _metadata, vector::similarity::%s(%s, %s) AS distance FROM %s WHERE %s <|2|> %s;',
            $this->vectorFieldName, $this->strategy, $this->vectorFieldName, $vectors, $this->table, $this->vectorFieldName, $vectors,
        ));

        foreach ($results[0]['result'] as $item) {
            yield $this->convertToVectorDocument($item);
        }
    }

    public function drop(array $options = []): void
    {
        $this->authenticate();

        $this->request('DELETE', \sprintf('key/%s', $this->table), []);
    }

    /**
     * @param array<string, mixed>|string $payload
     *
     * @return array<string|int, mixed>
     */
    private function request(string $method, string $endpoint, array|string $payload): array
    {
        $url = \sprintf('%s/%s', $this->endpointUrl, $endpoint);

        $finalPayload = [];

        if (\is_array($payload) && [] !== $payload) {
            $finalPayload = [
                'json' => $payload,
            ];
        }

        if (\is_string($payload)) {
            $finalPayload = [
                'body' => $payload,
            ];
        }

        $response = $this->httpClient->request($method, $url, [
            ...$finalPayload,
            ...[
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Surreal-NS' => $this->namespace,
                    'Surreal-DB' => $this->database,
                    'Authorization' => \sprintf('Bearer %s', $this->authenticationToken),
                ],
            ],
        ]);

        return $response->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function convertToIndexableArray(VectorDocument $document): array
    {
        return [
            'id' => $document->getId(),
            $this->vectorFieldName => $document->getVector()->getData(),
            '_metadata' => array_merge($document->getMetadata()->getArrayCopy(), [
                '_id' => $document->getId(),
            ]),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function convertToVectorDocument(array $data): VectorDocument
    {
        $id = $data['_metadata']['_id'] ?? throw new InvalidArgumentException('Missing "id" field in the document data.');

        $vector = !\array_key_exists($this->vectorFieldName, $data) || null === $data[$this->vectorFieldName]
            ? new NullVector()
            : new Vector($data[$this->vectorFieldName]);

        unset($data['_metadata']['_id']);

        return new VectorDocument(
            id: $id,
            vector: $vector,
            metadata: new Metadata($data['_metadata']),
        );
    }

    private function authenticate(): void
    {
        if ('' !== $this->authenticationToken) {
            return;
        }

        $authenticationPayload = [
            'user' => $this->user,
            'pass' => $this->password,
        ];

        if ($this->isNamespacedUser) {
            $authenticationPayload['ns'] = $this->namespace;
            $authenticationPayload['db'] = $this->database;
        }

        $authenticationResponse = $this->httpClient->request('POST', \sprintf('%s/signin', $this->endpointUrl), [
            'headers' => [
                'Accept' => 'application/json',
            ],
            'json' => $authenticationPayload,
        ]);

        $payload = $authenticationResponse->toArray();

        if (!\array_key_exists('token', $payload)) {
            throw new RuntimeException('The SurrealDB authentication response does not contain a token.');
        }

        $this->authenticationToken = $payload['token'];
    }
}
