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
use Symfony\AI\Chat\MessageStoreInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final readonly class MessageStore implements ManagedStoreInterface, MessageStoreInterface
{
    public function __construct(
        private \Redis $redis,
        private string $indexName,
        private SerializerInterface $serializer,
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

    public function save(MessageBag $messages): void
    {
        $this->redis->set($this->indexName, json_encode(array_map(
            fn (MessageInterface $message): string => $this->serializer->serialize($message, 'json'),
            $messages->getMessages(),
        )));
    }

    public function load(): MessageBag
    {
        return new MessageBag(...array_map(
            fn (string $message): MessageInterface => $this->serializer->deserialize($message, MessageInterface::class, 'json'),
            json_decode($this->redis->get($this->indexName))),
        );
    }
}
