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
use Symfony\AI\Platform\Message\Content\WebSearch;
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
     * Responses input is a flat list of output items, so text is buffered until a structured item
     * fixes the next position in that list.
     *
     * @param AssistantMessage $data
     *
     * @return list<array<string, mixed>>
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $items = [];
        $text = '';

        foreach ($data->getContent() as $part) {
            if ($part instanceof Text) {
                $text .= $part->getText();

                continue;
            }

            if ($part instanceof Thinking) {
                $item = $this->toReasoningItem($part);

                if (null === $item) {
                    continue;
                }

                $this->flushText($items, $text, $data);
                $items[] = $item;

                continue;
            }

            if ($part instanceof WebSearch) {
                $item = self::toWebSearchItem($part);

                if (null === $item) {
                    continue;
                }

                $this->flushText($items, $text, $data);
                $items[] = $item;

                continue;
            }

            if ($part instanceof ToolCall) {
                $this->flushText($items, $text, $data);

                /** @var array<string, mixed> $toolCall */
                $toolCall = $this->normalizer->normalize($part, $format, $context);
                $items[] = $toolCall;
            }
        }

        $this->flushText($items, $text, $data);

        if ([] === $items) {
            $items[] = self::message($data, null);
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
     * @return array<string, mixed>|null
     */
    private static function toWebSearchItem(WebSearch $webSearch): ?array
    {
        $signature = $webSearch->getSignature();

        if (null === $signature) {
            return null;
        }

        try {
            $item = json_decode($signature, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!\is_array($item) || 'web_search_call' !== ($item['type'] ?? null)) {
            return null;
        }

        /* @var array<string, mixed> $item */
        return $item;
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function flushText(array &$items, string &$text, AssistantMessage $data): void
    {
        if ('' === $text) {
            return;
        }

        $items[] = self::message($data, $text);
        $text = '';
    }

    /**
     * @return array<string, mixed>
     */
    private static function message(AssistantMessage $data, ?string $content): array
    {
        return [
            'role' => $data->getRole()->value,
            'type' => 'message',
            'content' => $content,
        ];
    }
}
