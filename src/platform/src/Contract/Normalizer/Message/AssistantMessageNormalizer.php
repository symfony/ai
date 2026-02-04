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
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerAwareTrait;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class AssistantMessageNormalizer implements NormalizerInterface, NormalizerAwareInterface, SerializerAwareInterface
{
    use NormalizerAwareTrait;
    use SerializerAwareTrait;

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
     * @return array{role: 'assistant', content?: string, tool_calls?: array<array<string, mixed>>}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $array = [
            'role' => $data->getRole()->value,
        ];

        $content = $data->getContent();
        if (null !== $content) {
            $array['content'] = $this->serializer->serialize($content, 'json', $context);
        }

        if ($data->hasToolCalls()) {
            $array['tool_calls'] = $this->normalizer->normalize($data->getToolCalls(), $format, $context);
        }

        return $array;
    }
}
