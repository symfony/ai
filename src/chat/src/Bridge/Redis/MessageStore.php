<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat\Bridge\Redis;

use Symfony\AI\Chat\ManagedStoreInterface;
use Symfony\AI\Chat\MessageBagNormalizer;
use Symfony\AI\Chat\MessageNormalizer;
use Symfony\AI\Chat\MessageStoreInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class MessageStore implements ManagedStoreInterface, MessageStoreInterface
{
    public function __construct(
        private readonly \Redis $redis,
        private readonly string $indexName,
        private readonly SerializerInterface $serializer = new Serializer([
            new ArrayDenormalizer(),
            new MessageBagNormalizer(new MessageNormalizer()),
            new MessageNormalizer(),
        ], [new JsonEncoder()]),
    ) {
    }

    public function setup(array $options = []): void
    {
        if ($this->redis->exists($this->indexName)) {
            return;
        }

        $this->redis->set($this->indexName, $this->serializer->serialize([], 'json'));
    }

    public function drop(): void
    {
        $this->redis->set($this->indexName, $this->serializer->serialize([], 'json'));
    }

    public function save(MessageBag $messages, ?string $identifier = null): void
    {
        $this->redis->set($identifier ?? $this->indexName, $this->serializer->serialize($messages, 'json'));
    }

    public function load(?string $identifier = null): MessageBag
    {
        $payload = \is_bool($this->redis->get($identifier ?? $this->indexName))
            ? $this->serializer->serialize([], 'json')
            : $this->redis->get($identifier ?? $this->indexName)
        ;

        return $this->serializer->deserialize($payload, MessageBag::class, 'json');
    }
}
