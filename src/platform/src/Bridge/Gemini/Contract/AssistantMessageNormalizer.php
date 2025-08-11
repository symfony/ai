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

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Model;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class AssistantMessageNormalizer extends ModelContractNormalizer
{
    protected function supportedDataClass(): string
    {
        return AssistantMessage::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model->supports(Capability::TOOL_CALLING);
    }

    /**
     * @param AssistantMessage $data
     *
     * @return array{array{text: string}}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $normalized = [];

        if (isset($data->content)) {
            $normalized['text'] = $data->content;
        }

        if (isset($data->toolCalls[0])) {
            $normalized['functionCall'] = [
                'id' => $data->toolCalls[0]->id,
                'name' => $data->toolCalls[0]->name,
            ];

            if ($data->toolCalls[0]->arguments) {
                $normalized['functionCall']['args'] = $data->toolCalls[0]->arguments;
            }
        }

        return [$normalized];
    }
}
