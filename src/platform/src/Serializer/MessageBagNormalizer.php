<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Serializer;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Lossless (de)normalization of a message bag, including its id and metadata.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @phpstan-import-type NormalizedMessage from MessageNormalizer
 */
final class MessageBagNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public function __construct(
        private readonly MessageNormalizer $messageNormalizer = new MessageNormalizer(),
    ) {
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array{id: string, metadata: array<string, mixed>, messages: list<NormalizedMessage>}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        if (!$data instanceof MessageBag) {
            throw new InvalidArgumentException(\sprintf('Expected an instance of "%s", "%s" given.', MessageBag::class, get_debug_type($data)));
        }

        return [
            'id' => $data->getId()->toRfc4122(),
            'metadata' => $data->getMetadata()->all(),
            'messages' => array_map(
                fn (MessageInterface $message): array => $this->messageNormalizer->normalize($message, $format, $context),
                $data->getMessages(),
            ),
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof MessageBag;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): MessageBag
    {
        if (!\is_array($data) || !isset($data['id'], $data['messages'])) {
            throw new InvalidArgumentException('The normalized message bag must be an array with an "id" and a "messages" key.');
        }

        /** @var AbstractUid&TimeBasedUidInterface&Uuid $id */
        $id = Uuid::fromString($data['id']);

        $messages = (new MessageBag(...array_map(
            fn (array $message): MessageInterface => $this->messageNormalizer->denormalize($message, MessageInterface::class, $format, $context),
            $data['messages'],
        )))->withId($id);

        if ([] !== ($data['metadata'] ?? [])) {
            $messages->getMetadata()->set($data['metadata']);
        }

        return $messages;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return MessageBag::class === $type;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            MessageBag::class => true,
        ];
    }
}
