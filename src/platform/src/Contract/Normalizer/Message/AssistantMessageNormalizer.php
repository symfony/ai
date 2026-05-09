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
        $array = [
            'role' => 'assistant',
            'content' => $data->getContent(),
        ];

        if ($data->hasToolCalls()) {
            $toolCalls = $this->normalizer->normalize($data->getToolCalls(), $format, $context);
            \assert(\is_array($toolCalls));
            /** @var array<array<string, mixed>> $toolCalls */
            $array['tool_calls'] = $toolCalls;
        }

        if (null !== $thinking = $data->getThinkingContent()) {
            $array['reasoning_content'] = $thinking;
        }

        return $array;
    }
}
