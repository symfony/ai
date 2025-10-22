<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat;

use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class MessageBagNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public function __construct(
        private readonly MessageNormalizer $messageNormalizer,
        private readonly ClockInterface $clock = new MonotonicClock(),
    ) {
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof MessageBag;
    }

    /**
     * @return array<string, mixed>
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        if (!$data instanceof MessageBag) {
            return [];
        }

        $idKey = \is_string($context['identifier'] ?? null) ? $context['identifier'] : 'id';

        return [
            $idKey => $data->getId()->toRfc4122(),
            'messages' => array_map(
                fn (MessageInterface $message): array => $this->messageNormalizer->normalize($message, $format, $context),
                $data->getMessages(),
            ),
            'addedAt' => $this->clock->now()->getTimestamp(),
        ];
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return MessageBag::class === $type;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): MessageBag
    {
        if (!\is_array($data) || [] === $data) {
            return new MessageBag();
        }

        /** @var list<array<string, mixed>> $rawMessages */
        $rawMessages = \is_array($data['messages'] ?? null) ? $data['messages'] : [];

        $messages = array_map(
            fn (array $message): MessageInterface => $this->messageNormalizer->denormalize($message, MessageInterface::class, $format, $context),
            $rawMessages,
        );

        $messageBag = new MessageBag(...$messages);

        $idKey = \is_string($context['identifier'] ?? null) ? $context['identifier'] : 'id';

        if (\is_string($data[$idKey] ?? null)) {
            /** @var AbstractUid&TimeBasedUidInterface&Uuid $existingUuid */
            $existingUuid = Uuid::fromRfc4122($data[$idKey]);

            return $messageBag->withId($existingUuid);
        }

        return $messageBag;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            MessageBag::class => true,
        ];
    }
}
