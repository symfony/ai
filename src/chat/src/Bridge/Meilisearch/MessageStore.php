<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat\Bridge\Meilisearch;

use Symfony\AI\Chat\Exception\InvalidArgumentException;
use Symfony\AI\Chat\ManagedStoreInterface;
use Symfony\AI\Chat\MessageBagNormalizer;
use Symfony\AI\Chat\MessageNormalizer;
use Symfony\AI\Chat\MessageStoreInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class MessageStore implements ManagedStoreInterface, MessageStoreInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $endpointUrl,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly ClockInterface $clock = new MonotonicClock(),
        private readonly string $indexName = '_message_store_meilisearch',
        private readonly SerializerInterface&NormalizerInterface&DenormalizerInterface $serializer = new Serializer([
            new ArrayDenormalizer(),
            new MessageBagNormalizer(new MessageNormalizer()),
            new MessageNormalizer(),
        ], [new JsonEncoder()]),
    ) {
    }

    public function setup(array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options.');
        }

        $this->createIndex($this->indexName);
    }

    public function drop(): void
    {
        $this->request('DELETE', \sprintf('indexes/%s/documents', $this->indexName));
    }

    public function save(MessageBag $messages, ?string $identifier = null): void
    {
        if (null !== $identifier) {
            $this->createIndex($identifier);
        }

        $this->request('POST', \sprintf('indexes/%s/documents', $identifier ?? $this->indexName), $this->serializer->normalize($messages));
    }

    public function load(?string $identifier = null): MessageBag
    {
        if (null !== $identifier) {
            $this->createIndex($identifier);
        }

        $messages = $this->request('POST', \sprintf('indexes/%s/documents/fetch', $identifier ?? $this->indexName), [
            'filter' => \sprintf('chat = %s', $identifier ?? $this->indexName),
            'sort' => ['addedAt:asc'],
        ]);

        return $this->serializer->denormalize($messages['results'][0] ?? [], MessageBag::class);
    }

    /**
     * @param array<string, mixed>|list<array<string, mixed>> $payload
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $endpoint, array $payload = []): array
    {
        $result = $this->httpClient->request($method, \sprintf('%s/%s', $this->endpointUrl, $endpoint), [
            'headers' => [
                'Authorization' => \sprintf('Bearer %s', $this->apiKey),
            ],
            'json' => [] !== $payload ? $payload : new \stdClass(),
        ]);

        $payload = $result->toArray();

        if (!\array_key_exists('status', $payload)) {
            return $payload;
        }

        if (\in_array($payload['status'], ['succeeded', 'failed'], true)) {
            return $payload;
        }

        $currentTaskStatusCallback = fn (): ResponseInterface => $this->httpClient->request('GET', \sprintf('%s/tasks/%s', $this->endpointUrl, $payload['taskUid']), [
            'headers' => [
                'Authorization' => \sprintf('Bearer %s', $this->apiKey),
            ],
        ]);

        while (!\in_array($currentTaskStatusCallback()->toArray()['status'], ['succeeded', 'failed'], true)) {
            $this->clock->sleep(1);
        }

        return $payload;
    }

    private function createIndex(string $indexName): void
    {
        $result = $this->httpClient->request('GET', \sprintf('%s/indexes/%s', $this->endpointUrl, $indexName), [
            'headers' => [
                'Authorization' => \sprintf('Bearer %s', $this->apiKey),
            ],
        ]);

        $payload = $result->toArray(false);

        if (\array_key_exists('uid', $payload)) {
            return;
        }

        $this->request('POST', 'indexes', [
            'uid' => $indexName,
            'primaryKey' => 'id',
        ]);

        $this->request('PATCH', \sprintf('indexes/%s/settings', $indexName), [
            'filterableAttributes' => [
                'chat',
            ],
            'sortableAttributes' => [
                'addedAt',
            ],
        ]);
    }
}
