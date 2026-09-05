<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenResponses\Contract\Message;

use Symfony\AI\Platform\Bridge\OpenResponses\ResponsesModel;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\Thinking;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

/**
 * @author Guillermo Lengemann <guillermo.lengemann@gmail.com>
 */
final class AssistantMessageNormalizer extends ModelContractNormalizer implements NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    /**
     * Responses input is a flat list of output items, so text is buffered until a reasoning item or
     * a tool call fixes the next position in that list.
     *
     * @param AssistantMessage $data
     *
     * @return list<array<string, mixed>>
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $items = [];
        $text = '';
        $signature = null;
        $messageIndex = 0;

        foreach ($data->getContent() as $part) {
            if ($part instanceof Text) {
                $text .= $part->getText();
                $signature ??= $part->getSignature();

                continue;
            }

            if ($part instanceof Thinking) {
                $item = $this->toReasoningItem($part);

                if (null === $item) {
                    continue;
                }

                $this->flushText($items, $text, $signature, $data, $messageIndex);
                $items[] = $item;

                continue;
            }

            if ($part instanceof ToolCall) {
                $this->flushText($items, $text, $signature, $data, $messageIndex);

                /** @var array<string, mixed> $toolCall */
                $toolCall = $this->normalizer->normalize($part, $format, $context);
                $items[] = $toolCall;
            }
        }

        $this->flushText($items, $text, $signature, $data, $messageIndex);

        if ([] === $items) {
            $items[] = self::message($data, null, null, 0);
        }

        return $items;
    }

    protected function supportedDataClass(): string
    {
        return AssistantMessage::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof ResponsesModel;
    }

    /**
     * The signature carries the provider's original reasoning output item; the summary text alone
     * is not accepted.
     *
     * @return array<string, mixed>|null
     */
    private function toReasoningItem(Thinking $part): ?array
    {
        $signature = $part->getSignature();

        if (null === $signature) {
            return null;
        }

        try {
            $item = json_decode($signature, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            // signatures from other providers may be opaque strings
            return null;
        }

        if (!\is_array($item) || 'reasoning' !== ($item['type'] ?? null)) {
            return null;
        }

        /* @var array<string, mixed> $item */
        return $item;
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function flushText(array &$items, string &$text, ?string &$signature, AssistantMessage $data, int &$messageIndex): void
    {
        if ('' === $text) {
            return;
        }

        $items[] = self::message($data, $text, $signature, $messageIndex);
        $text = '';
        $signature = null;
        ++$messageIndex;
    }

    /**
     * @return array<string, mixed>
     */
    private static function message(AssistantMessage $data, ?string $content, ?string $signature, int $messageIndex): array
    {
        return [
            'role' => $data->getRole()->value,
            'type' => 'message',
            'id' => self::messageId($data, $signature, $messageIndex),
            'status' => 'completed',
            'content' => null === $content ? [] : [
                ['type' => 'output_text', 'text' => $content, 'annotations' => []],
            ],
        ];
    }

    /**
     * Strict Responses implementations validate the replayed item against the full output message
     * shape, which carries an id. The provider's own id is used whenever the message came back from
     * one; a message authored locally - a stored greeting, or one assembled from a stream - has no
     * counterpart on the provider side and falls back to its own uuid, made unique per emitted item
     * because a single assistant turn can flush more than one message.
     */
    private static function messageId(AssistantMessage $data, ?string $signature, int $messageIndex): string
    {
        $providerId = self::providerMessageId($signature);
        if (null !== $providerId) {
            return $providerId;
        }

        $id = 'msg_'.str_replace('-', '', $data->getId()->toRfc4122());

        return 0 === $messageIndex ? $id : $id.\sprintf('%02d', $messageIndex);
    }

    /**
     * Text signatures are provider-scoped: only the message item this bridge recorded in
     * {@see \Symfony\AI\Platform\Bridge\OpenResponses\ResultConverter} is understood, opaque
     * signatures from other providers are ignored.
     */
    private static function providerMessageId(?string $signature): ?string
    {
        if (null === $signature) {
            return null;
        }

        try {
            $item = json_decode($signature, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!\is_array($item) || 'message' !== ($item['type'] ?? null)) {
            return null;
        }

        $id = $item['id'] ?? null;

        return \is_string($id) ? $id : null;
    }
}
