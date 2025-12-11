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
use Symfony\AI\Chat\Exception\RuntimeException;
use Symfony\AI\Chat\ManagedStoreInterface;
use Symfony\AI\Chat\MessageNormalizer;
use Symfony\AI\Chat\MessageStoreInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\Component\Clock\ClockInterface;
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
        private readonly ClockInterface $clock,
        private readonly string $indexName = '_message_store_meilisearch',
        private readonly SerializerInterface&NormalizerInterface&DenormalizerInterface $serializer = new Serializer([
            new ArrayDenormalizer(),
            new MessageNormalizer(),
        ], [new JsonEncoder()]),
    ) {
        if (!interface_exists(ClockInterface::class)) {
            throw new RuntimeException('For using Meilisearch as a message store , symfony/clock is required. Try running "composer require symfony/clock".');
        }
    }

    public function setup(array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options.');
        }

        $this->request('POST', 'indexes', [
            'uid' => $this->indexName,
            'primaryKey' => 'id',
        ]);

        $this->request('PATCH', \sprintf('indexes/%s/settings', $this->indexName), [
            'sortableAttributes' => [
                'addedAt',
            ],
        ]);
    }

    public function save(MessageBag $messages): void
    {
        $messages = $messages->getMessages();

        $this->request('PUT', \sprintf('indexes/%s/documents', $this->indexName), array_map(
            fn (MessageInterface $message): array => $this->serializer->normalize($message),
            $messages,
        ));
    }

    public function load(): MessageBag
    {
        $messages = $this->request('POST', \sprintf('indexes/%s/documents/fetch', $this->indexName), [
            'sort' => ['addedAt:asc'],
        ]);

        return new MessageBag(...array_map(
            fn (array $message): MessageInterface => $this->serializer->denormalize($message, MessageInterface::class),
            $messages['results']
        ));
    }

    public function drop(): void
    {
        $this->request('DELETE', \sprintf('indexes/%s/documents', $this->indexName));
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

        while ('succeeded' !== $currentTaskStatusCallback()->toArray()['status']) {
            $this->clock->sleep(1);
        }

        return $payload;
    }
}
