<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Meilisearch;

use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\MessageStore\ConflictDetector;
use Symfony\AI\Agent\MessageStore\ManagedMessageStoreInterface;
use Symfony\AI\Agent\MessageStore\MessageStoreInterface;
use Symfony\AI\Agent\Toolbox\Source\SourceCollectionNormalizer;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Serializer\MessageBagNormalizer;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Keeps each conversation in a document; Meilisearch has no conditional writes, so saving checks the stored messages first.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class MessageStore implements ManagedMessageStoreInterface, MessageStoreInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $endpointUrl,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly ClockInterface $clock,
        private readonly string $indexName = '_message_store_meilisearch',
        private readonly SerializerInterface $serializer = new Serializer([
            new MessageBagNormalizer(),
            new SourceCollectionNormalizer(),
        ], [new JsonEncoder()]),
    ) {
    }

    public function setup(array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options.');
        }

        $task = $this->request('POST', 'indexes', [
            'uid' => $this->indexName,
            'primaryKey' => 'id',
        ]);

        if ('index_already_exists' !== ($task['error']['code'] ?? null)) {
            $this->assertSucceeded($task);
        }
    }

    public function delete(AbstractUid&TimeBasedUidInterface $id): void
    {
        $this->assertSucceeded($this->request('DELETE', \sprintf('indexes/%s/documents/%s', $this->indexName, $id->toRfc4122())));
    }

    public function load(AbstractUid&TimeBasedUidInterface $id): MessageBag
    {
        $response = $this->httpClient->request('GET', \sprintf('%s/indexes/%s/documents/%s', $this->endpointUrl, $this->indexName, $id->toRfc4122()), [
            'auth_bearer' => $this->apiKey,
        ]);

        if (404 === $response->getStatusCode()) {
            return MessageBag::forId($id);
        }

        return $this->serializer->deserialize($response->toArray()['messages'], MessageBag::class, 'json');
    }

    public function save(MessageBag $messages): void
    {
        ConflictDetector::assertContinues($this->load($messages->getId()), $messages);

        $this->assertSucceeded($this->request('PUT', \sprintf('indexes/%s/documents', $this->indexName), [[
            'id' => $messages->getId()->toRfc4122(),
            'version' => ConflictDetector::version($messages),
            'messages' => $this->serializer->serialize($messages, 'json'),
        ]]));
    }

    public function drop(): void
    {
        $this->assertSucceeded($this->request('DELETE', \sprintf('indexes/%s/documents', $this->indexName)));
    }

    /**
     * @param array<string, mixed>|list<array<string, mixed>> $payload
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $endpoint, array $payload = []): array
    {
        $result = $this->httpClient->request($method, \sprintf('%s/%s', $this->endpointUrl, $endpoint), [
            'auth_bearer' => $this->apiKey,
            'json' => [] !== $payload ? $payload : new \stdClass(),
        ]);

        $payload = $result->toArray();

        if (!\array_key_exists('taskUid', $payload)) {
            return $payload;
        }

        do {
            $task = $this->httpClient->request('GET', \sprintf('%s/tasks/%s', $this->endpointUrl, $payload['taskUid']), [
                'auth_bearer' => $this->apiKey,
            ])->toArray();

            if (\in_array($task['status'], ['enqueued', 'processing'], true)) {
                $this->clock->sleep(0.1);
            }
        } while (\in_array($task['status'], ['enqueued', 'processing'], true));

        return $task;
    }

    /**
     * @param array<string, mixed> $task
     */
    private function assertSucceeded(array $task): void
    {
        if ('failed' === ($task['status'] ?? null)) {
            throw new RuntimeException(\sprintf('The Meilisearch task "%s" failed: "%s"', $task['uid'] ?? $task['taskUid'] ?? '', $task['error']['message'] ?? 'unknown error'));
        }
    }
}
