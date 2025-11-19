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
     * @return array{array{text: string}}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $normalized = [];

        if (null !== $data->getContent()) {
            $normalized['text'] = $data->getContent();
        }

        if ($data->hasToolCalls()) {
            $normalized['functionCall'] = [
                'id' => $data->getToolCalls()[0]->getId(),
                'name' => $data->getToolCalls()[0]->getName(),
            ];

            if ($data->getToolCalls()[0]->getArguments()) {
                $normalized['functionCall']['args'] = $data->getToolCalls()[0]->getArguments();
            }
        }

        return [$normalized];
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
