<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Contract;

use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\Thinking;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Replays a reasoning turn the way Mistral asks for it, as a thinking chunk of the content list.
 *
 * The generic contract sends `reasoning_content`, which Mistral rejects with a 422
 * "Extra inputs are not permitted". Its own shape is a content list of `thinking` and `text`
 * chunks - the one {@see \Symfony\AI\Platform\Bridge\Mistral\Llm\ResultConverter} reads back -
 * and the reasoning has to come along: "The model relies on the reasoning trace to maintain
 * coherence across turns."
 *
 * @see https://docs.mistral.ai/capabilities/reasoning/
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class AssistantMessageNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof AssistantMessage;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            AssistantMessage::class => true,
        ];
    }

    /**
     * @param AssistantMessage $data
     *
     * @return array{
     *     role: 'assistant',
     *     content: string|list<array{type: 'thinking', thinking: list<array{type: 'text', text: string}>}|array{type: 'text', text: string}>|null,
     *     tool_calls?: array<array<string, mixed>>,
     * }
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        /** @var list<array{type: 'thinking'|'text', text: string}> $chunks */
        $chunks = [];
        $toolCalls = [];
        $hasThinking = false;

        foreach ($data->getContent() as $part) {
            if ($part instanceof ToolCall) {
                $toolCalls[] = $part;

                continue;
            }

            if ($part instanceof Text) {
                self::append($chunks, 'text', $part->getText());

                continue;
            }

            if ($part instanceof Thinking && self::append($chunks, 'thinking', $part->getContent())) {
                $hasThinking = true;
            }
        }

        $array = [
            'role' => $data->getRole()->value,
            // a turn without reasoning keeps the plain string every other Mistral model expects
            'content' => $hasThinking ? self::chunks($chunks) : self::plainText($chunks),
        ];

        if ([] !== $toolCalls) {
            $array['tool_calls'] = $this->normalizer->normalize($toolCalls, $format, $context);
        }

        return $array;
    }

    /**
     * Merges into the previous chunk when it is of the same type; an empty block is not replayed.
     *
     * @param list<array{type: 'thinking'|'text', text: string}> $chunks
     * @param 'thinking'|'text'                                  $type
     */
    private static function append(array &$chunks, string $type, string $text): bool
    {
        if ('' === $text) {
            return false;
        }

        $last = array_key_last($chunks);

        if (null !== $last && $type === $chunks[$last]['type']) {
            $chunks[$last]['text'] .= $text;

            return true;
        }

        $chunks[] = ['type' => $type, 'text' => $text];

        return true;
    }

    /**
     * @param list<array{type: 'thinking'|'text', text: string}> $chunks
     *
     * @return list<array{type: 'thinking', thinking: list<array{type: 'text', text: string}>}|array{type: 'text', text: string}>
     */
    private static function chunks(array $chunks): array
    {
        $content = [];

        foreach ($chunks as $chunk) {
            $content[] = 'thinking' === $chunk['type']
                ? ['type' => 'thinking', 'thinking' => [['type' => 'text', 'text' => $chunk['text']]]]
                : ['type' => 'text', 'text' => $chunk['text']];
        }

        return $content;
    }

    /**
     * @param list<array{type: 'thinking'|'text', text: string}> $chunks
     */
    private static function plainText(array $chunks): ?string
    {
        $text = '';

        foreach ($chunks as $chunk) {
            if ('text' === $chunk['type']) {
                $text .= $chunk['text'];
            }
        }

        return '' === $text ? null : $text;
    }
}
