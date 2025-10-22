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
     * @return array{
     *     id: string,
     *     forkedFrom: ?string,
     *     messages: array<string, mixed>
     * }
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return [
            $context['identifier'] ?? 'id' => $data->getId()->toRfc4122(),
            'chat' => $data->getChat(),
            'messages' => array_map(
                fn (MessageInterface $message): array => [
                    ...$this->messageNormalizer->normalize($message, $format, $context),
                    'bag' => $data->getId()->toRfc4122(),
                ],
                $data->getMessages(),
            ),
            'addedAt' => $this->clock->now()->getTimestamp(),
            'metadata' => [
                'id' => $data->getId()->toRfc4122(),
            ],
        ];
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return MessageBag::class === $type;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): MessageBag
    {
        if ([] === $data) {
            return new MessageBag();
        }

        $messages = array_map(
            fn (array $message): MessageInterface => $this->messageNormalizer->denormalize($message, MessageInterface::class, $format, $context),
            $data['messages'] ?? [],
        );

        $messageBag = new MessageBag(...$messages);
        $messageBag->setChat($data['chat']);

        /** @var AbstractUid&TimeBasedUidInterface&Uuid $existingUuid */
        $existingUuid = Uuid::fromRfc4122($data['metadata']['id']);

        return $messageBag->withId($existingUuid);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            MessageBag::class => true,
        ];
    }
}
