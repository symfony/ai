<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\VertexAi\Contract;

use Symfony\AI\Platform\Bridge\VertexAi\Gemini\Model;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Model as BaseModel;

/**
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 */
final class AssistantMessageNormalizer extends ModelContractNormalizer
{
    /**
     * @param AssistantMessage $data
     *
     * @return array{
     *     array{
     *         text: string,
     *         functionCall?: array{
     *             name: string,
     *             args?: array<int|string, mixed>
     *         }
     *     }
     * }
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $normalized = [];

        if (null !== $data->getContent()) {
            $normalized[] = ['text' => $data->getContent()];
        }

        if ($data->hasToolCalls()) {
            $normalized['functionCall'] = [
                'name' => $data->getToolCalls()[0]->getName(),
            ];

            if ($data->getToolCalls()[0]->getArguments()) {
                $normalized['functionCall']['args'] = $data->getToolCalls()[0]->getArguments();
            }
        }

        return $normalized;
    }

    protected function supportedDataClass(): string
    {
        return AssistantMessage::class;
    }

    protected function supportsModel(BaseModel $model): bool
    {
        return $model instanceof Model;
    }
}
