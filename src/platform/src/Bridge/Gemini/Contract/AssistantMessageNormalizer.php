<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Contract;

use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Model;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class AssistantMessageNormalizer extends ModelContractNormalizer
{
    /**
     * @param AssistantMessage $data
     *
     * @return list<array<string, mixed>>
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $parts = [];

        // Add thoughtSignature as first part if present (required for Gemini 3+ thinking models)
        if (null !== $data->getThinkingSignature()) {
            $thinkingPart = ['thoughtSignature' => $data->getThinkingSignature()];
            if (null !== $data->getThinkingContent()) {
                $thinkingPart['thought'] = true;
                $thinkingPart['text'] = $data->getThinkingContent();
            }
            $parts[] = $thinkingPart;
        }

        if (null !== $data->getContent()) {
            $parts[] = ['text' => $data->getContent()];
        }

        if ($data->hasToolCalls()) {
            foreach ($data->getToolCalls() as $toolCall) {
                $functionCall = [
                    'id' => $toolCall->getId(),
                    'name' => $toolCall->getName(),
                ];

                if ([] !== $toolCall->getArguments()) {
                    $functionCall['args'] = $toolCall->getArguments();
                }

                $parts[] = ['functionCall' => $functionCall];
            }
        }

        // If no parts were added, return empty text part
        if ([] === $parts) {
            return [['text' => '']];
        }

        return $parts;
    }

    protected function supportedDataClass(): string
    {
        return AssistantMessage::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Gemini;
    }
}
