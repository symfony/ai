<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\AiMlApi\Contract;

use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ThinkingResult;
use Symfony\AI\Platform\Result\ToolCallResult;
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
     * @return array{role: 'assistant', content: string, tool_calls?: array<array<string, mixed>>, reasoning_content?: string}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $content = $data->getContent();

        if ($content instanceof MultiPartResult) {
            $array = [
                'role' => $data->getRole()->value,
                'content' => '',
            ];

            foreach ($content as $part) {
                if ($part instanceof TextResult) {
                    $array['content'] .= $part->getContent();
                } elseif ($part instanceof ToolCallResult) {
                    $array['tool_calls'] = array_merge($array['tool_calls'] ?? [], $this->normalizer->normalize($part->getContent(), $format, $context));
                } elseif ($part instanceof ThinkingResult) {
                    $array['reasoning_content'] = ($array['reasoning_content'] ?? '').$part->getContent();
                }
            }

            return $array;
        }

        $content = $data->getContent()?->getContent();

        return [
            'role' => $data->getRole()->value,
            'content' => $content,
        ];
    }
}
