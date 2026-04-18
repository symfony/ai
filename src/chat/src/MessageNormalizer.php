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
use Symfony\AI\Platform\Message\Content\Thinking;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class MessageNormalizer implements NormalizerInterface, DenormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

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
            AssistantMessage::class => new AssistantMessage(...self::denormalizeAssistantParts($data)),
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
        $parts = [];
        $content = '';

        if ($data instanceof AssistantMessage) {
            $content = $data->asText() ?? '';
            $parts = $this->normalizeAssistantParts($data, $format, $context);
            if ($data->hasToolCalls()) {
                $toolsCalls = $this->normalizer->normalize($data->getToolCalls(), $format, $context);
            }
        } elseif ($data instanceof SystemMessage) {
            $content = $data->getContent();
        } elseif ($data instanceof ToolCallMessage) {
            $content = $data->getContent();
            $toolsCalls = $this->normalizer->normalize($data->getToolCall(), $format, $context);
        }

        return [
            $context['identifier'] ?? 'id' => $data->getId()->toRfc4122(),
            'type' => $data::class,
            'content' => $content,
            'contentAsBase64' => ($data instanceof UserMessage && [] !== $data->getContent()) ? array_map(
                static fn (ContentInterface $content) => [
                    'type' => $content::class,
                    'content' => match ($content::class) {
                        Text::class => $content->getText(),
                        File::class,
                        Document::class,
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
            'parts' => $parts,
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

    /**
     * @param array<string, mixed> $context
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeAssistantParts(AssistantMessage $message, ?string $format, array $context): array
    {
        $parts = [];
        foreach ($message->getContent() as $part) {
            if ($part instanceof Text) {
                $parts[] = ['type' => Text::class, 'text' => $part->getText()];
            } elseif ($part instanceof Thinking) {
                $parts[] = ['type' => Thinking::class, 'content' => $part->getContent(), 'signature' => $part->getSignature()];
            } elseif ($part instanceof ToolCall) {
                $parts[] = ['type' => ToolCall::class, 'toolCall' => $this->normalizer->normalize($part, $format, $context)];
            }
        }

        return $parts;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<ContentInterface>
     */
    private static function denormalizeAssistantParts(array $data): array
    {
        if (isset($data['parts']) && [] !== $data['parts']) {
            $parts = [];
            foreach ($data['parts'] as $part) {
                $parts[] = match ($part['type']) {
                    Text::class => new Text($part['text']),
                    Thinking::class => new Thinking($part['content'] ?? '', $part['signature'] ?? null),
                    ToolCall::class => new ToolCall(
                        $part['toolCall']['id'],
                        $part['toolCall']['function']['name'],
                        json_decode($part['toolCall']['function']['arguments'], true),
                    ),
                    default => throw new LogicException(\sprintf('Unknown assistant part type "%s".', $part['type'])),
                };
            }

            return $parts;
        }

        // Legacy format: content + toolsCalls (no ordering preserved).
        $parts = [];
        $content = $data['content'] ?? '';
        if ('' !== $content) {
            $parts[] = new Text($content);
        }

        foreach ($data['toolsCalls'] ?? [] as $toolCall) {
            $parts[] = new ToolCall(
                $toolCall['id'],
                $toolCall['function']['name'],
                json_decode($toolCall['function']['arguments'], true),
            );
        }

        return $parts;
    }
}
