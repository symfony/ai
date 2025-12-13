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

use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ToolCallMessageNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof ToolCallMessage;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            ToolCallMessage::class => true,
        ];
    }

    /**
     * @param ToolCallMessage $data
     *
     * @return array{
     *     role: 'tool',
     *     content: string|list<array<string, mixed>>,
     *     tool_call_id: string,
     * }
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $contents = $data->getContent();

        // If only text content, use simple string format for backwards compatibility
        if ($this->isTextOnly($contents)) {
            return [
                'role' => $data->getRole()->value,
                'content' => $data->asText() ?? '',
                'tool_call_id' => $data->getToolCall()->getId(),
            ];
        }

        // Multimodal content: normalize each content item
        $normalizedContent = [];
        foreach ($contents as $content) {
            $normalizedContent[] = $this->normalizer->normalize($content, $format, $context);
        }

        return [
            'role' => $data->getRole()->value,
            'content' => $normalizedContent,
            'tool_call_id' => $data->getToolCall()->getId(),
        ];
    }

    /**
     * @param array<mixed> $contents
     */
    private function isTextOnly(array $contents): bool
    {
        foreach ($contents as $content) {
            if (!$content instanceof Text) {
                return false;
            }
        }

        return true;
    }
}
