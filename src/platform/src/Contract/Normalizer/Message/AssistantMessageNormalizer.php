<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\Normalizer\Message;

use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\Thinking;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
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
     * Provider specific parts - a web search, an MCP call, a code execution, ... - are dropped when
     * a model is bound to the context, since the payload is then a provider request the part does
     * not belong to. Without a model, the payload is a provider agnostic representation of the
     * message (used e.g. to derive a cache key), so the parts are delegated to the serializer and
     * emitted under "content_parts" rather than being silently lost.
     *
     * @param AssistantMessage $data
     *
     * @return array{role: 'assistant', content: string|null, tool_calls?: array<array<string, mixed>>, reasoning_content?: string, content_parts?: array<array<string, mixed>>}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $text = '';
        $reasoning = '';
        $toolCalls = [];
        $additional = [];

        foreach ($data->getContent() as $part) {
            if ($part instanceof Text) {
                $text .= $part->getText();
            } elseif ($part instanceof Thinking) {
                $reasoning .= $part->getContent();
            } elseif ($part instanceof ToolCall) {
                $toolCalls[] = $part;
            } elseif (!isset($context[Contract::CONTEXT_MODEL])) {
                $additional[] = $this->normalizer->normalize($part, $format, $context);
            }
        }

        $array = [
            'role' => $data->getRole()->value,
            'content' => '' === $text ? null : $text,
        ];

        if ([] !== $toolCalls) {
            $array['tool_calls'] = $this->normalizer->normalize($toolCalls, $format, $context);
        }

        if ('' !== $reasoning) {
            $array['reasoning_content'] = $reasoning;
        }

        if ([] !== $additional) {
            $array['content_parts'] = $additional;
        }

        return $array;
    }
}
