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
     * @param AssistantMessage $data
     *
     * @return array{role: 'assistant', content: string|null, tool_calls?: array<array<string, mixed>>, reasoning_content?: string}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $text = '';
        $reasoning = '';
        $toolCalls = [];

        foreach ($data->getContent() as $part) {
            if ($part instanceof Text) {
                $text .= $part->getText();
            } elseif ($part instanceof Thinking) {
                $reasoning .= $part->getContent();
            } elseif ($part instanceof ToolCall) {
                $toolCalls[] = $part;
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

        return $array;
    }
}
