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
use Symfony\AI\Chat\MessageNormalizer;
use Symfony\AI\Chat\MessageStoreInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
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
        private readonly string $collectionName,
        private readonly SerializerInterface&NormalizerInterface&DenormalizerInterface $serializer = new Serializer([
            new ArrayDenormalizer(),
            new MessageNormalizer(),
        ], [new JsonEncoder()]),
    ) {
    }

    public function setup(array $options = []): void
    {
        $database = $this->client->getDatabase($this->databaseName);

        $database->createCollection($this->collectionName, $options);
    }

    public function drop(): void
    {
        $this->client->getCollection($this->databaseName, $this->collectionName)->deleteMany([
            'q' => [],
        ]);
    }

    public function save(MessageBag $messages): void
    {
        $currentCollection = $this->client->getCollection($this->databaseName, $this->collectionName);

        $currentCollection->insertMany(array_map(
            fn (MessageInterface $message): array => $this->serializer->normalize($message, context: [
                'identifier' => '_id',
            ]),
            $messages->getMessages(),
        ));
    }

    public function load(): MessageBag
    {
        $currentCollection = $this->client->getCollection($this->databaseName, $this->collectionName);

        $cursor = $currentCollection->find([], [
            'typeMap' => [
                'root' => 'array',
                'document' => 'array',
                'array' => 'array',
            ],
        ]);

        return new MessageBag(...array_map(
            fn (array $message): MessageInterface => $this->serializer->denormalize($message, MessageInterface::class, context: [
                'identifier' => '_id',
            ]),
            $cursor->toArray(),
        ));
    }
}
