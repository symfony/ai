<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat\Bridge\MongoDb;

use MongoDB\Client;
use Symfony\AI\Chat\ManagedStoreInterface;
use Symfony\AI\Chat\MessageBagNormalizer;
use Symfony\AI\Chat\MessageNormalizer;
use Symfony\AI\Chat\MessageStoreInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class MessageStore implements ManagedStoreInterface, MessageStoreInterface
{
    public function __construct(
        private readonly Client $client,
        private readonly string $databaseName,
        private readonly string $collectionName = '_message_store_mongodb',
        private readonly SerializerInterface&NormalizerInterface&DenormalizerInterface $serializer = new Serializer([
            new ArrayDenormalizer(),
            new MessageBagNormalizer(new MessageNormalizer()),
            new MessageNormalizer(),
        ], [new JsonEncoder()]),
    ) {
    }

    public function setup(array $options = []): void
    {
        $this->createCollection($this->collectionName, $options);
    }

    public function drop(): void
    {
        $this->client->getCollection($this->databaseName, $this->collectionName)->deleteMany([
            'q' => [],
        ]);
    }

    public function save(MessageBag $messages, ?string $identifier = null): void
    {
        if (null !== $identifier) {
            $this->createCollection($identifier);
        }

        $currentCollection = $this->client->getCollection($this->databaseName, $identifier ?? $this->collectionName);

        $payload = $this->serializer->normalize($messages, context: [
            'identifier' => '_id',
        ]);

        $documentExist = $currentCollection->findOne([
            '_id' => $payload['_id'],
        ]);

        null === $documentExist ? $currentCollection->insertOne($payload) : $currentCollection->replaceOne([
            '_id' => $payload['_id'],
        ], $payload);
    }

    public function load(?string $identifier = null): MessageBag
    {
        $currentCollection = $this->client->getCollection($this->databaseName, $identifier ?? $this->collectionName);

        $cursor = $currentCollection->findOne([], [
            'typeMap' => [
                'root' => 'array',
                'document' => 'array',
                'array' => 'array',
            ],
        ]);

        return $this->serializer->denormalize($cursor, MessageBag::class, context: [
            'identifier' => '_id',
        ]);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function createCollection(string $collectionName, array $options = []): void
    {
        $database = $this->client->getDatabase($this->databaseName);

        foreach ($database->listCollectionNames() as $collection) {
            if ($collection === $collectionName) {
                return;
            }
        }

        $database->createCollection($collectionName, $options);
    }
}
