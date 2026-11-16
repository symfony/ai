<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\MongoDb;

use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Driver\Exception\BulkWriteException;
use Symfony\AI\Agent\Exception\ConflictException;
use Symfony\AI\Agent\MessageStore\ConflictDetector;
use Symfony\AI\Agent\MessageStore\ManagedMessageStoreInterface;
use Symfony\AI\Agent\MessageStore\MessageStoreInterface;
use Symfony\AI\Agent\Toolbox\Source\SourceCollectionNormalizer;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Serializer\MessageBagNormalizer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;

/**
 * Keeps each conversation in a document, updated only while its version is the one that was loaded.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class MessageStore implements ManagedMessageStoreInterface, MessageStoreInterface
{
    private const DUPLICATE_KEY = 11000;

    public function __construct(
        private readonly Client $client,
        private readonly string $databaseName,
        private readonly string $collectionName,
        private readonly SerializerInterface $serializer = new Serializer([
            new MessageBagNormalizer(),
            new SourceCollectionNormalizer(),
        ], [new JsonEncoder()]),
    ) {
    }

    public function setup(array $options = []): void
    {
        $this->client->getDatabase($this->databaseName)->createCollection($this->collectionName, $options);
    }

    public function drop(): void
    {
        $this->collection()->deleteMany([]);
    }

    public function delete(AbstractUid&TimeBasedUidInterface $id): void
    {
        $this->collection()->deleteOne(['_id' => $id->toRfc4122()]);
    }

    public function load(AbstractUid&TimeBasedUidInterface $id): MessageBag
    {
        $document = $this->find($id);

        if (null === $document) {
            return MessageBag::forId($id);
        }

        return $this->serializer->deserialize($document['messages'], MessageBag::class, 'json');
    }

    public function save(MessageBag $messages): void
    {
        $document = $this->find($messages->getId());
        $stored = null === $document ? (new MessageBag())->withId($messages->getId()) : $this->serializer->deserialize($document['messages'], MessageBag::class, 'json');

        ConflictDetector::assertContinues($stored, $messages);

        $id = $messages->getId()->toRfc4122();
        $values = [
            'version' => ConflictDetector::version($messages),
            'messages' => $this->serializer->serialize($messages, 'json'),
        ];

        if (null === $document) {
            try {
                $this->collection()->insertOne(['_id' => $id, ...$values]);
            } catch (BulkWriteException $exception) {
                if (self::DUPLICATE_KEY !== ($exception->getWriteResult()->getWriteErrors()[0] ?? null)?->getCode()) {
                    throw $exception;
                }

                throw ConflictException::forMessages($messages, $exception);
            }

            return;
        }

        $result = $this->collection()->updateOne(['_id' => $id, 'version' => $document['version']], ['$set' => $values]);

        if (0 === $result->getMatchedCount()) {
            throw ConflictException::forMessages($messages);
        }
    }

    /**
     * @return array{version: string, messages: string}|null
     */
    private function find(AbstractUid $id): ?array
    {
        return $this->collection()->findOne(['_id' => $id->toRfc4122()], [
            'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array'],
        ]);
    }

    private function collection(): Collection
    {
        return $this->client->getCollection($this->databaseName, $this->collectionName);
    }
}
