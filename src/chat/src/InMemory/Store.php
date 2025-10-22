<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat\InMemory;

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
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Store implements ManagedStoreInterface, MessageStoreInterface
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $messages = [];

    public function __construct(
        private readonly string $identifier = '_message_store_memory',
        private readonly SerializerInterface&NormalizerInterface&DenormalizerInterface $serializer = new Serializer([
            new ArrayDenormalizer(),
            new MessageBagNormalizer(new MessageNormalizer()),
            new MessageNormalizer(),
        ], [new JsonEncoder()]),
    ) {
    }

    public function setup(array $options = []): void
    {
        $this->messages = [];
    }

    public function save(MessageBag $messages, ?string $identifier = null): void
    {
        $this->messages[$identifier ?? $this->identifier] = $this->serializer->normalize($messages);
    }

    public function load(?string $identifier = null): MessageBag
    {
        $bag = $this->messages[$identifier ?? $this->identifier] ?? [];

        return $this->serializer->denormalize($bag, MessageBag::class);
    }

    public function drop(): void
    {
        $this->messages = [];
    }
}
