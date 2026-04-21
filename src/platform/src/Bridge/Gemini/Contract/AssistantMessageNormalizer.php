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
     * @return list<array{text: string}|array{functionCall: array{id: string, name: string, args?: mixed}}>
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $normalized = [];

        // text and functionCall are separate oneof fields in Gemini's
        // they must be emitted as distinct parts, never merged into one.
        if (null !== $data->getContent()) {
            $normalized[] = ['text' => $data->getContent()];
        }

        if ($data->hasToolCalls()) {
            $toolCall = $data->getToolCalls()[0];
            $functionCall = [
                'id' => $toolCall->getId(),
                'name' => $toolCall->getName(),
            ];

            if ($toolCall->getArguments()) {
                $functionCall['args'] = $toolCall->getArguments();
            }

            $normalized[] = ['functionCall' => $functionCall];
        }

        return $normalized;
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
