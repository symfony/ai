<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat\Bridge\Session;

use Symfony\AI\Chat\ManagedStoreInterface;
use Symfony\AI\Chat\MessageBagNormalizer;
use Symfony\AI\Chat\MessageNormalizer;
use Symfony\AI\Chat\MessageStoreInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class MessageStore implements ManagedStoreInterface, MessageStoreInterface
{
    private SessionInterface $session;

    public function __construct(
        RequestStack $requestStack,
        private readonly string $sessionKey = 'messages',
        private SerializerInterface&NormalizerInterface&DenormalizerInterface $serializer = new Serializer([
            new ArrayDenormalizer(),
            new MessageBagNormalizer(new MessageNormalizer()),
            new MessageNormalizer(),
        ], [new JsonEncoder()]),
    ) {
        $this->session = $requestStack->getSession();
    }

    public function setup(array $options = []): void
    {
        $this->session->set($this->sessionKey, []);
    }

    public function save(MessageBag $messages, ?string $identifier = null): void
    {
        $this->session->set($identifier ?? $this->sessionKey, $this->serializer->normalize($messages));
    }

    public function load(?string $identifier = null): MessageBag
    {
        $messages = $this->session->get($identifier ?? $this->sessionKey, []);

        return $this->serializer->denormalize($messages, MessageBag::class);
    }

    public function drop(): void
    {
        $this->session->remove($this->sessionKey);
    }
}
