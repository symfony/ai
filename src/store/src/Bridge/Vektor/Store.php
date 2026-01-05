<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Vektor;

use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
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
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly string $endpoint,
    ) {
    }

    public function setup(array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options.');
        }
    }

    public function drop(array $options = []): void
    {
        // TODO: Implement drop() method.
    }

    public function add(VectorDocument|array $documents): void
    {
        if ($documents instanceof VectorDocument) {
            $documents = [$documents];
        }

        foreach ($documents as $document) {
            $this->request('POST', 'insert', [
                'id' => '',
                'vector' => $document->vector->getData(),
                'metadata' => $document->metadata->getArrayCopy(),
            ]);
        }
    }

    public function remove(array|string $ids, array $options = []): void
    {
        if (\is_string($ids)) {
            $ids = [$ids];
        }

        foreach ($ids as $id) {
            $this->request('DELETE', 'delete', [
                'id' => $id,
            ]);
        }
    }

    public function query(Vector $vector, array $options = []): iterable
    {
        $results = $this->request('GET', 'search', [
            'vector' => $vector->getData(),
            'k' => $options['k'] ?? 10,
        ]);

    }

    private function request(string $method, string $endpoint, array $payload): array
    {
        $response = $this->httpClient->request($method, \sprintf('%s/%s', $this->endpoint, $endpoint), [
            'auth_bearer' => $this->apiKey,
            'json' => $payload,
        ]);

        return $response->toArray();
    }
}
