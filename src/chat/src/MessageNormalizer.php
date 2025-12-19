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
use Symfony\AI\Platform\Message\Content\DocumentUrl;
use Symfony\AI\Platform\Message\Content\File;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\ImageUrl;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\ToolCall;
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
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        if ([] === $data) {
            throw new InvalidArgumentException('The current message bag data are not coherent.');
        }

        $type = $data['type'];
        $content = $data['content'] ?? '';
        $contentAsBase64 = $data['contentAsBase64'] ?? [];

        $message = match ($type) {
            SystemMessage::class => new SystemMessage($content),
            AssistantMessage::class => new AssistantMessage($content, array_map(
                static fn (array $toolsCall): ToolCall => new ToolCall(
                    $toolsCall['id'],
                    $toolsCall['function']['name'],
                    json_decode($toolsCall['function']['arguments'], true)
                ),
                $data['toolsCalls'],
            )),
            UserMessage::class => new UserMessage(...array_map(
                static fn (array $contentAsBase64): ContentInterface => \in_array($contentAsBase64['type'], [File::class, Image::class, Audio::class], true)
                    ? $contentAsBase64['type']::fromDataUrl($contentAsBase64['content'])
                    : new $contentAsBase64['type']($contentAsBase64['content']),
                $contentAsBase64,
            )),
            ToolCallMessage::class => new ToolCallMessage(
                new ToolCall(
                    $data['toolsCalls']['id'],
                    $data['toolsCalls']['function']['name'],
                    json_decode($data['toolsCalls']['function']['arguments'], true)
                ),
                $content
            ),
            default => throw new LogicException(\sprintf('Unknown message type "%s".', $type)),
        };

        $identifier = $context['identifier'] ?? 'id';
        /** @var AbstractUid&TimeBasedUidInterface&Uuid $existingUuid */
        $existingUuid = Uuid::fromString($data[$identifier]);

        $messageWithExistingUuid = $message->withId($existingUuid);

        $messageWithExistingUuid->getMetadata()->set([
            ...$data['metadata'],
            'addedAt' => $data['addedAt'],
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
                $data->getToolCalls(),
            );
        }

        if ($data instanceof ToolCallMessage) {
            $toolsCalls = $data->getToolCall()->jsonSerialize();
        }

        return [
            $context['identifier'] ?? 'id' => $data->getId()->toRfc4122(),
            'type' => $data::class,
            'content' => ($data instanceof SystemMessage || $data instanceof AssistantMessage || $data instanceof ToolCallMessage) ? $data->getContent() : '',
            'contentAsBase64' => ($data instanceof UserMessage && [] !== $data->getContent()) ? array_map(
                static fn (ContentInterface $content) => [
                    'type' => $content::class,
                    'content' => match ($content::class) {
                        Text::class => $content->getText(),
                        File::class,
                        Image::class,
                        Audio::class => $content->asBase64(),
                        ImageUrl::class,
                        DocumentUrl::class => $content->getUrl(),
                        default => throw new LogicException(\sprintf('Unknown content type "%s".', $content::class)),
                    },
                ],
                $data->getContent(),
            ) : [],
            'toolsCalls' => $toolsCalls,
            'metadata' => $data->getMetadata()->all(),
            'addedAt' => (new \DateTimeImmutable())->getTimestamp(),
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
}
