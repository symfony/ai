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
     *     content: string,
     *     tool_call_id: string,
     * }
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return [
            'role' => $data->getRole()->value,
            'content' => $this->normalizer->normalize($data->getContent(), $format, $context),
            'tool_call_id' => $data->getToolCall()->getId(),
        ];
    }
}
