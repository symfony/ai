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
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Content\CodeExecution;
use Symfony\AI\Platform\Message\Content\Collection;
use Symfony\AI\Platform\Message\Content\Commentary;
use Symfony\AI\Platform\Message\Content\ComputerCall;
use Symfony\AI\Platform\Message\Content\ContentInterface;
use Symfony\AI\Platform\Message\Content\CustomToolCall;
use Symfony\AI\Platform\Message\Content\Document;
use Symfony\AI\Platform\Message\Content\DocumentUrl;
use Symfony\AI\Platform\Message\Content\ExecutableCode;
use Symfony\AI\Platform\Message\Content\File;
use Symfony\AI\Platform\Message\Content\FileSearch;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\ImageUrl;
use Symfony\AI\Platform\Message\Content\LocalShellCall;
use Symfony\AI\Platform\Message\Content\McpApprovalRequest;
use Symfony\AI\Platform\Message\Content\McpCall;
use Symfony\AI\Platform\Message\Content\McpListTools;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\Thinking;
use Symfony\AI\Platform\Message\Content\Video;
use Symfony\AI\Platform\Message\Content\WebSearch;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\Template;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Lossless (de)normalization of messages for persisting them, e.g. in a message store.
 *
 * Use the context key "identifier" to change the name of the id field, e.g. "_id" for MongoDB.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @phpstan-type NormalizedPart array{type: class-string, ...<string, mixed>}
 * @phpstan-type NormalizedMessage array{
 *     type: class-string<MessageInterface>,
 *     content: string,
 *     parts: list<NormalizedPart>,
 *     toolCall: array<string, mixed>|null,
 *     metadata: array<string, mixed>,
 *     addedAt: int,
 *     ...<string, mixed>
 * }
 */
final class MessageNormalizer implements NormalizerInterface, DenormalizerInterface, NormalizerAwareInterface, DenormalizerAwareInterface
{
    private readonly MetadataNormalizer $metadataNormalizer;

    public function __construct()
    {
        $this->metadataNormalizer = new MetadataNormalizer();
    }

    public function setNormalizer(NormalizerInterface $normalizer): void
    {
        $this->metadataNormalizer->setNormalizer($normalizer);
    }

    public function setDenormalizer(DenormalizerInterface $denormalizer): void
    {
        $this->metadataNormalizer->setDenormalizer($denormalizer);
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return NormalizedMessage
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        if (!$data instanceof MessageInterface) {
            throw new InvalidArgumentException(\sprintf('Expected an instance of "%s", "%s" given.', MessageInterface::class, get_debug_type($data)));
        }

        if ($data instanceof SystemMessage) {
            $content = (string) $data->getContent();
            $parts = $data->getContent() instanceof Template ? [$this->normalizePart($data->getContent())] : [];
        } elseif ($data instanceof UserMessage || $data instanceof AssistantMessage || $data instanceof ToolCallMessage) {
            $content = $data->asText() ?? '';
            $parts = array_map($this->normalizePart(...), $data->getContent());
        } else {
            throw new InvalidArgumentException(\sprintf('Unsupported message type "%s".', $data::class));
        }

        $ignored = $context[AbstractNormalizer::IGNORED_ATTRIBUTES] ?? [];

        return array_diff_key([
            $context['identifier'] ?? 'id' => $data->getId()->toRfc4122(),
            'type' => $data::class,
            'content' => $content,
            'parts' => $parts,
            'toolCall' => $data instanceof ToolCallMessage ? $this->normalizePart($data->getToolCall()) : null,
            'metadata' => \in_array('metadata', $ignored, true) ? [] : $this->metadataNormalizer->normalize($data->getMetadata()->all(), $format, $context),
            'addedAt' => $data->getId()->getDateTime()->getTimestamp(),
        ], array_flip($ignored));
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof MessageInterface;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): MessageInterface
    {
        if (!\is_array($data) || !isset($data['type'])) {
            throw new InvalidArgumentException('The normalized message must be an array with a "type" key.');
        }

        // the legacy format kept user and tool call content in "contentAsBase64"
        $parts = array_map($this->denormalizePart(...), ($data['parts'] ?? []) ?: ($data['contentAsBase64'] ?? []));

        $message = match ($data['type']) {
            SystemMessage::class => new SystemMessage($parts[0] ?? $data['content'] ?? ''),
            UserMessage::class => new UserMessage(...$parts),
            AssistantMessage::class => new AssistantMessage(...([] !== $parts ? $parts : $this->denormalizeLegacyAssistantParts($data))),
            ToolCallMessage::class => $this->denormalizeToolCallMessage($data, $parts),
            default => throw new InvalidArgumentException(\sprintf('Unknown message type "%s".', $data['type'])),
        };

        /** @var AbstractUid&TimeBasedUidInterface&Uuid $id */
        $id = Uuid::fromString($data[$context['identifier'] ?? 'id']);

        $message = $message->withId($id);
        if ([] !== ($data['metadata'] ?? [])) {
            $message->getMetadata()->set($this->metadataNormalizer->denormalize($data['metadata'], $format, $context));
        }

        return $message;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return MessageInterface::class === $type || is_subclass_of($type, MessageInterface::class);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            MessageInterface::class => true,
        ];
    }

    /**
     * @return NormalizedPart
     */
    private function normalizePart(ContentInterface $part): array
    {
        return ['type' => $part::class] + match (true) {
            $part instanceof Text => ['text' => $part->getText(), 'signature' => $part->getSignature(), 'phase' => $part->getPhase()],
            $part instanceof Thinking => ['content' => $part->getContent(), 'signature' => $part->getSignature()],
            $part instanceof Commentary => ['content' => $part->getContent()],
            $part instanceof ToolCall => ['id' => $part->getId(), 'name' => $part->getName(), 'arguments' => $part->getArguments(), 'signature' => $part->getSignature()],
            $part instanceof Template => ['template' => $part->getTemplate(), 'templateType' => $part->getType()],
            $part instanceof File => ['data' => $part->asDataUrl()],
            $part instanceof ImageUrl, $part instanceof DocumentUrl => ['url' => $part->getUrl()],
            $part instanceof Collection => ['content' => array_map($this->normalizePart(...), $part->getContent())],
            $part instanceof ExecutableCode => ['code' => $part->getCode(), 'language' => $part->getLanguage(), 'id' => $part->getId()],
            $part instanceof CodeExecution => ['succeeded' => $part->isSucceeded(), 'output' => $part->getOutput(), 'id' => $part->getId()],
            $part instanceof WebSearch => ['query' => $part->getQuery(), 'id' => $part->getId(), 'status' => $part->getStatus(), 'queries' => $part->getQueries(), 'signature' => $part->getSignature()],
            $part instanceof FileSearch => ['queries' => $part->getQueries(), 'results' => $part->getResults(), 'id' => $part->getId(), 'status' => $part->getStatus()],
            $part instanceof McpCall => ['serverLabel' => $part->getServerLabel(), 'name' => $part->getName(), 'arguments' => $part->getArguments(), 'output' => $part->getOutput(), 'error' => $part->getError(), 'id' => $part->getId(), 'status' => $part->getStatus()],
            $part instanceof McpListTools => ['serverLabel' => $part->getServerLabel(), 'tools' => $part->getTools(), 'id' => $part->getId()],
            $part instanceof McpApprovalRequest => ['serverLabel' => $part->getServerLabel(), 'name' => $part->getName(), 'arguments' => $part->getArguments(), 'id' => $part->getId()],
            $part instanceof CustomToolCall => ['name' => $part->getName(), 'input' => $part->getInput(), 'id' => $part->getId(), 'status' => $part->getStatus()],
            $part instanceof ComputerCall => ['action' => $part->getAction(), 'callId' => $part->getCallId(), 'pendingSafetyChecks' => $part->getPendingSafetyChecks(), 'id' => $part->getId(), 'status' => $part->getStatus()],
            $part instanceof LocalShellCall => ['command' => $part->getCommand(), 'callId' => $part->getCallId(), 'id' => $part->getId(), 'status' => $part->getStatus()],
            default => throw new InvalidArgumentException(\sprintf('Unsupported content type "%s".', $part::class)),
        };
    }

    /**
     * @param array<string, mixed> $part
     */
    private function denormalizePart(array $part): ContentInterface
    {
        return match ($part['type']) {
            Text::class => new Text($part['text'] ?? $part['content'], $part['signature'] ?? null, $part['phase'] ?? null),
            Thinking::class => new Thinking($part['content'] ?? '', $part['signature'] ?? null),
            Commentary::class => new Commentary($part['content'] ?? ''),
            ToolCall::class => $this->denormalizeToolCall($part['toolCall'] ?? $part, $part['signature'] ?? null),
            Template::class => new Template($part['template'], $part['templateType']),
            File::class, Document::class, Image::class, Audio::class, Video::class => $part['type']::fromDataUrl($part['data'] ?? $part['content']),
            ImageUrl::class => new ImageUrl($part['url'] ?? $part['content']),
            DocumentUrl::class => new DocumentUrl($part['url'] ?? $part['content']),
            Collection::class => new Collection(...array_map($this->denormalizePart(...), $part['content'])),
            ExecutableCode::class => new ExecutableCode($part['code'], $part['language'], $part['id']),
            CodeExecution::class => new CodeExecution($part['succeeded'], $part['output'], $part['id']),
            WebSearch::class => new WebSearch($part['query'], $part['id'], $part['status'], $part['queries'], $part['signature']),
            FileSearch::class => new FileSearch($part['queries'], $part['results'], $part['id'], $part['status']),
            McpCall::class => new McpCall($part['serverLabel'], $part['name'], $part['arguments'], $part['output'], $part['error'], $part['id'], $part['status']),
            McpListTools::class => new McpListTools($part['serverLabel'], $part['tools'], $part['id']),
            McpApprovalRequest::class => new McpApprovalRequest($part['serverLabel'], $part['name'], $part['arguments'], $part['id']),
            CustomToolCall::class => new CustomToolCall($part['name'], $part['input'], $part['id'], $part['status']),
            ComputerCall::class => new ComputerCall($part['action'], $part['callId'], $part['pendingSafetyChecks'], $part['id'], $part['status']),
            LocalShellCall::class => new LocalShellCall($part['command'], $part['callId'], $part['id'], $part['status']),
            default => throw new InvalidArgumentException(\sprintf('Unsupported content type "%s".', $part['type'])),
        };
    }

    /**
     * Supports the current shape and the legacy one with a "function" key holding JSON encoded arguments.
     *
     * @param array<string, mixed> $toolCall
     */
    private function denormalizeToolCall(array $toolCall, ?string $signature = null): ToolCall
    {
        if (isset($toolCall['function'])) {
            return new ToolCall(
                $toolCall['id'],
                $toolCall['function']['name'],
                json_decode($toolCall['function']['arguments'], true, flags: \JSON_THROW_ON_ERROR),
                $toolCall['signature'] ?? $signature,
            );
        }

        return new ToolCall($toolCall['id'], $toolCall['name'], $toolCall['arguments'], $toolCall['signature'] ?? $signature);
    }

    /**
     * @param array<string, mixed>   $data
     * @param list<ContentInterface> $parts
     */
    private function denormalizeToolCallMessage(array $data, array $parts): ToolCallMessage
    {
        if (isset($data['toolCall'])) {
            return new ToolCallMessage($this->denormalizeToolCall($data['toolCall']), ...$parts);
        }

        return new ToolCallMessage(
            $this->denormalizeToolCall($data['toolsCalls']),
            ...([] !== $parts ? $parts : [new Text($data['content'] ?? '')]),
        );
    }

    /**
     * Messages stored without parts only kept the text and the tool calls, without their order.
     *
     * @param array<string, mixed> $data
     *
     * @return list<ContentInterface>
     */
    private function denormalizeLegacyAssistantParts(array $data): array
    {
        $parts = [];
        if ('' !== ($data['content'] ?? '')) {
            $parts[] = new Text($data['content']);
        }

        foreach ($data['toolsCalls'] ?? [] as $toolCall) {
            $parts[] = $this->denormalizeToolCall($toolCall);
        }

        return $parts;
    }
}
