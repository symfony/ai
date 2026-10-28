<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Redis;

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
 * Keeps each conversation under its own key, saved with an optimistic WATCH / MULTI transaction.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class MessageStore implements ManagedMessageStoreInterface, MessageStoreInterface
{
    public function __construct(
        private readonly \Redis $redis,
        private readonly string $indexName,
        private readonly SerializerInterface $serializer = new Serializer([
            new MessageBagNormalizer(),
            new SourceCollectionNormalizer(),
        ], [new JsonEncoder()]),
    ) {
    }

    public function setup(array $options = []): void
    {
    }

    public function drop(): void
    {
        // SCAN neither prefixes its pattern nor strips the prefix from the keys it returns
        $prefix = (string) $this->redis->getOption(\Redis::OPT_PREFIX);
        $iterator = null;

        do {
            $keys = $this->redis->scan($iterator, addcslashes($prefix.$this->indexName, '\\*?[]').':'.str_repeat('?', 36));

            if (\is_array($keys) && [] !== $keys) {
                $this->redis->del(array_map(static fn (string $key): string => substr($key, \strlen($prefix)), $keys));
            }
        } while (0 < $iterator);
    }

    public function load(AbstractUid&TimeBasedUidInterface $id): MessageBag
    {
        $payload = $this->redis->get($this->key($id));

        if (!\is_string($payload)) {
            return MessageBag::forId($id);
        }

        return $this->serializer->deserialize($payload, MessageBag::class, 'json');
    }

    public function save(MessageBag $messages): void
    {
        $key = $this->key($messages->getId());
        $payload = $this->serializer->serialize($messages, 'json');

        $this->redis->watch($key);
        $watching = true;

        try {
            ConflictDetector::assertContinues($this->load($messages->getId()), $messages);

            $watching = false;
            $result = $this->redis->multi()
                ->set($key, $payload)
                ->exec();
        } finally {
            if ($watching) {
                $this->redis->unwatch();
            }
        }

        if (!\is_array($result)) {
            throw ConflictException::forMessages($messages);
        }
    }

    public function delete(AbstractUid&TimeBasedUidInterface $id): void
    {
        $this->redis->del($this->key($id));
    }

    private function key(AbstractUid $id): string
    {
        return $this->indexName.':'.$id->toRfc4122();
    }
}
