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

use Symfony\AI\Chat\Exception\LogicException;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Content\ContentInterface;
use Symfony\AI\Platform\Message\Content\Document;
use Symfony\AI\Platform\Message\Content\DocumentUrl;
use Symfony\AI\Platform\Message\Content\File;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\ImageUrl;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\Video;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class MessageNormalizer implements NormalizerInterface, DenormalizerInterface
{
    private const CONTENT_TYPES_FROM_DATA_URL = [
        File::class,
        Image::class,
        Audio::class,
        Document::class,
        Video::class,
    ];

    private const CONTENT_TYPES_FROM_CONSTRUCTOR = [
        Text::class,
        ImageUrl::class,
        DocumentUrl::class,
    ];

    public function __construct(
        private readonly ClockInterface $clock = new MonotonicClock(),
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        if (!\is_array($data) || [] === $data) {
            throw new InvalidArgumentException('The current message bag data are not coherent.');
        }

        if (!\is_string($data['type'] ?? null)) {
            throw new InvalidArgumentException('The message type must be a string.');
        }

        $type = $data['type'];
        $content = \is_string($data['content'] ?? null) ? $data['content'] : '';

        /** @var list<array{type: class-string<ContentInterface>, content: string}> $contentAsBase64 */
        $contentAsBase64 = \is_array($data['contentAsBase64'] ?? null) ? $data['contentAsBase64'] : [];

        $message = match ($type) {
            SystemMessage::class => new SystemMessage($content),
            AssistantMessage::class => new AssistantMessage($content, self::denormalizeToolCallsList($data)),
            UserMessage::class => new UserMessage(...array_map(
                static function (array $contentAsBase64): ContentInterface {
                    if (\in_array($contentAsBase64['type'], self::CONTENT_TYPES_FROM_DATA_URL, true)) {
                        return $contentAsBase64['type']::fromDataUrl($contentAsBase64['content']);
                    }

                    if (\in_array($contentAsBase64['type'], self::CONTENT_TYPES_FROM_CONSTRUCTOR, true)) {
                        return new ($contentAsBase64['type'])($contentAsBase64['content']);
                    }

                    throw new LogicException(\sprintf('Unknown content type "%s".', $contentAsBase64['type']));
                },
                $contentAsBase64,
            )),
            ToolCallMessage::class => new ToolCallMessage(
                self::denormalizeToolCall($data),
                $content
            ),
            default => throw new LogicException(\sprintf('Unknown message type "%s".', $type)),
        };

        $idKey = \is_string($context['identifier'] ?? null) ? $context['identifier'] : 'id';

        if (!\is_string($data[$idKey] ?? null)) {
            throw new InvalidArgumentException(\sprintf('The message identifier key "%s" must contain a string value.', $idKey));
        }

        /** @var AbstractUid&TimeBasedUidInterface&Uuid $existingUuid */
        $existingUuid = Uuid::fromString($data[$idKey]);

        $messageWithExistingUuid = $message->withId($existingUuid);

        /** @var array<string, mixed> $metadata */
        $metadata = \is_array($data['metadata'] ?? null) ? $data['metadata'] : [];

        $messageWithExistingUuid->getMetadata()->set([
            ...$metadata,
            'addedAt' => $data['addedAt'] ?? null,
        ]);

        return $messageWithExistingUuid;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return MessageInterface::class === $type;
    }

    /**
     * @return array<string, mixed>
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        if (!$data instanceof MessageInterface) {
            return [];
        }

        $toolsCalls = [];

        if ($data instanceof AssistantMessage && $data->hasToolCalls()) {
            $toolsCalls = array_map(
                static fn (ToolCall $toolCall): array => $toolCall->jsonSerialize(),
                $data->getToolCalls() ?? [],
            );
        }

        if ($data instanceof ToolCallMessage) {
            $toolsCalls = $data->getToolCall()->jsonSerialize();
        }

        $idKey = \is_string($context['identifier'] ?? null) ? $context['identifier'] : 'id';

        return [
            $idKey => $data->getId()->toRfc4122(),
            'type' => $data::class,
            'content' => ($data instanceof SystemMessage || $data instanceof AssistantMessage || $data instanceof ToolCallMessage) ? $data->getContent() : '',
            'contentAsBase64' => ($data instanceof UserMessage && [] !== $data->getContent()) ? array_map(
                static fn (ContentInterface $content) => [
                    'type' => $content::class,
                    'content' => match ($content::class) {
                        Text::class => $content->getText(),
                        File::class,
                        Document::class,
                        Image::class,
                        Audio::class,
                        Video::class => $content->asBase64(),
                        ImageUrl::class,
                        DocumentUrl::class => $content->getUrl(),
                        default => throw new LogicException(\sprintf('Unknown content type "%s".', $content::class)),
                    },
                ],
                $data->getContent(),
            ) : [],
            'toolsCalls' => $toolsCalls,
            'metadata' => $data->getMetadata()->all(),
            'addedAt' => $this->clock->now()->getTimestamp(),
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof MessageInterface;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            MessageInterface::class => true,
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<ToolCall>
     */
    private static function denormalizeToolCallsList(array $data): array
    {
        /** @var list<array{id: string, function: array{name: string, arguments: string}}> $toolsCalls */
        $toolsCalls = \is_array($data['toolsCalls'] ?? null) ? $data['toolsCalls'] : [];

        return array_map(
            static function (array $toolsCall): ToolCall {
                /** @var array<string, mixed> $arguments */
                $arguments = json_decode($toolsCall['function']['arguments'], true);

                return new ToolCall($toolsCall['id'], $toolsCall['function']['name'], $arguments);
            },
            $toolsCalls,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function denormalizeToolCall(array $data): ToolCall
    {
        /** @var array{id: string, function: array{name: string, arguments: string}} $toolsCall */
        $toolsCall = \is_array($data['toolsCalls'] ?? null) ? $data['toolsCalls'] : [];

        /** @var array<string, mixed> $arguments */
        $arguments = json_decode($toolsCall['function']['arguments'], true);

        return new ToolCall($toolsCall['id'], $toolsCall['function']['name'], $arguments);
    }
}
