<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Cloudflare;

use Symfony\AI\Platform\Vector\NullVector;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class Store implements ManagedStoreInterface, StoreInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $accountId,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly string $index,
        private readonly int $dimensions = 1536,
        private readonly string $metric = 'cosine',
        private readonly string $endpointUrl = 'https://api.cloudflare.com/client/v4/accounts',
    ) {
    }

    public function setup(array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options.');
        }

        $this->request('POST', 'vectorize/v2/indexes', [
            'config' => [
                'dimensions' => $this->dimensions,
                'metric' => $this->metric,
            ],
            'name' => $this->index,
        ]);
    }

    public function drop(array $options = []): void
    {
        $this->request('DELETE', \sprintf('vectorize/v2/indexes/%s', $this->index));
    }

    public function add(VectorDocument ...$documents): void
    {
        $payload = array_map(
            $this->convertToIndexableArray(...),
            $documents,
        );

        $this->request('POST', \sprintf('vectorize/v2/indexes/%s/upsert', $this->index), function () use ($payload) {
            foreach ($payload as $entry) {
                yield json_encode($entry).\PHP_EOL;
            }
        });
    }

    public function query(Vector $vector, array $options = []): iterable
    {
        $results = $this->request('POST', \sprintf('vectorize/v2/indexes/%s/query', $this->index), [
            'vector' => $vector->getData(),
            'returnValues' => true,
            'returnMetadata' => 'all',
        ]);

        foreach ($results['result']['matches'] as $item) {
            yield $this->convertToVectorDocument($item);
        }
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $endpoint, \Closure|array $payload = []): array
    {
        $url = \sprintf('%s/%s/%s', $this->endpointUrl, $this->accountId, $endpoint);

        $options = [
            'auth_bearer' => $this->apiKey,
        ];

        if ($payload instanceof \Closure) {
            $options['headers'] = [
                'Content-Type' => 'application/x-ndjson',
            ];

            $options['body'] = $payload();
        }

        if (\is_array($payload)) {
            $options['json'] = $payload;
        }

        $response = $this->httpClient->request($method, $url, $options);

        return $response->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function convertToIndexableArray(VectorDocument $document): array
    {
        return [
            'id' => $document->id->toRfc4122(),
            'values' => $document->vector->getData(),
            'metadata' => $document->metadata->getArrayCopy(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function convertToVectorDocument(array $data): VectorDocument
    {
        $id = $data['id'] ?? throw new InvalidArgumentException('Missing "id" field in the document data.');

        $vector = !\array_key_exists('values', $data) || null === $data['values']
            ? new NullVector()
            : new Vector($data['values']);

        return new VectorDocument(
            id: Uuid::fromString($id),
            vector: $vector,
            metadata: new Metadata($data['metadata']),
            score: $data['score'] ?? null
        );
    }
}
