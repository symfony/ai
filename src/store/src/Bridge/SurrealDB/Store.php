<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\SurrealDB;

use Symfony\AI\Platform\Vector\NullVector;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\AI\Store\InitializableStoreInterface;
use Symfony\AI\Store\VectorStoreInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class Store implements InitializableStoreInterface, VectorStoreInterface
{
    private const MAXIMUM_EMBEDDINGS_DIMENSIONS = 1275;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $endpointUrl,
        #[\SensitiveParameter]
        private readonly string $user,
        #[\SensitiveParameter]
        private readonly string $password,
        #[\SensitiveParameter]
        private readonly string $namespace,
        #[\SensitiveParameter]
        private readonly string $database,
        private readonly string $table = 'vectors',
        private readonly string $vectorFieldName = '_vectors',
        private readonly string $strategy = 'cosine',
        private readonly int $embeddingsDimension = self::MAXIMUM_EMBEDDINGS_DIMENSIONS,
    ) {
    }

    public function add(VectorDocument ...$documents): void
    {
        $authenticationToken = $this->authenticate([]);

        foreach ($documents as $document) {
            if (self::MAXIMUM_EMBEDDINGS_DIMENSIONS < $document->vector->getDimensions()) {
                throw new InvalidArgumentException(\sprintf('The SurrealDB HTTP API does not support embeddings with more than %d dimensions, found %d', self::MAXIMUM_EMBEDDINGS_DIMENSIONS, $document->vector->getDimensions()));
            }

            $this->request('POST', \sprintf('key/%s', $this->table), $this->convertToIndexableArray($document), [
                'Surreal-NS' => $this->namespace,
                'Surreal-DB' => $this->database,
                'Authorization' => \sprintf('Bearer %s', $authenticationToken),
            ]);
        }
    }

    public function query(Vector $vector, array $options = [], ?float $minScore = null): array
    {
        if (self::MAXIMUM_EMBEDDINGS_DIMENSIONS < $vector->getDimensions()) {
            throw new InvalidArgumentException(\sprintf('The dimensions of the vector must be less than or equal to %d, found %d', self::MAXIMUM_EMBEDDINGS_DIMENSIONS, $vector->getDimensions()));
        }

        $authenticationToken = $this->authenticate($options);

        $vectors = json_encode($vector->getData());

        $results = $this->request('POST', 'sql', \sprintf(
            'SELECT id, %s, _metadata, vector::similarity::%s(%s, %s) AS distance FROM %s WHERE %s <|2|> %s;',
            $this->vectorFieldName, $this->strategy, $this->vectorFieldName, $vectors, $this->table, $this->vectorFieldName, $vectors,
        ), [
            'Surreal-NS' => $this->namespace,
            'Surreal-DB' => $this->database,
            'Authorization' => \sprintf('Bearer %s', $authenticationToken),
        ]);

        return array_map($this->convertToVectorDocument(...), $results[0]['result']);
    }

    public function initialize(array $options = []): void
    {
        $authenticationToken = $this->authenticate($options);

        $this->request('POST', 'sql', \sprintf(
            'DEFINE INDEX %s_vectors ON %s FIELDS %s MTREE DIMENSION %d DIST %s TYPE F32',
            $this->table, $this->table, $this->vectorFieldName, $this->embeddingsDimension, $this->strategy
        ), [
            'Surreal-NS' => $this->namespace,
            'Surreal-DB' => $this->database,
            'Authorization' => \sprintf('Bearer %s', $authenticationToken),
        ]);
    }

    /**
     * @param array<string, mixed>|string $payload
     * @param array<string, mixed>        $extraHeaders
     *
     * @return array<string|int, mixed>
     */
    private function request(string $method, string $endpoint, array|string $payload, array $extraHeaders = []): array
    {
        $url = \sprintf('%s/%s', $this->endpointUrl, $endpoint);

        $finalPayload = [
            'json' => $payload,
        ];

        if (\is_string($payload)) {
            $finalPayload = [
                'body' => $payload,
            ];
        }

        $response = $this->httpClient->request($method, $url, array_merge($finalPayload, [
            'headers' => array_merge($extraHeaders, [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]),
        ]));

        return $response->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function convertToIndexableArray(VectorDocument $document): array
    {
        return [
            'id' => $document->id->toRfc4122(),
            $this->vectorFieldName => $document->vector->getData(),
            '_metadata' => array_merge($document->metadata->getArrayCopy(), [
                '_id' => $document->id->toRfc4122(),
            ]),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function convertToVectorDocument(array $data): VectorDocument
    {
        $id = $data['_metadata']['_id'] ?? throw new InvalidArgumentException('Missing "id" field in the document data');

        $vector = !\array_key_exists($this->vectorFieldName, $data) || null === $data[$this->vectorFieldName]
            ? new NullVector()
            : new Vector($data[$this->vectorFieldName]);

        unset($data['_metadata']['_id']);

        return new VectorDocument(
            id: Uuid::fromString($id),
            vector: $vector,
            metadata: new Metadata(array_merge($data['_metadata'], [
                $this->vectorFieldName => $data[$this->vectorFieldName],
            ])),
        );
    }

    /**
     * @param array{
     *     namespacedUser?: bool
     * } $options The namespacedUser option is used to determine if the user is root or not, if not, both the namespace and database must be specified
     */
    private function authenticate(array $options): string
    {
        $authenticationPayload = [
            'user' => $this->user,
            'pass' => $this->password,
        ];

        if (\array_key_exists('namespacedUser', $options) && !$options['namespacedUser']) {
            $authenticationPayload['ns'] = $this->namespace;
            $authenticationPayload['db'] = $this->database;
        }

        $authenticationResponse = $this->request('POST', 'signin', $authenticationPayload);

        if (!\array_key_exists('token', $authenticationResponse)) {
            throw new RuntimeException('The SurrealDB authentication response does not contain a token.');
        }

        return $authenticationResponse['token'];
    }
}
