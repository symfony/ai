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
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Model as BaseModel;

/**
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 */
final class ToolCallMessageNormalizer extends ModelContractNormalizer
{
    /**
     * @param ToolCallMessage $data
     *
     * @return array{
     *      functionResponse: array{
     *          name: string,
     *          response: array<int|string, mixed>
     *      }
     *  }[]
     *
     * @throws \JsonException
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $resultContent = json_validate($data->getContent()) ? json_decode($data->getContent(), true, 512, \JSON_THROW_ON_ERROR) : $data->getContent();

        return [[
            'functionResponse' => array_filter([
                'name' => $data->getToolCall()->getName(),
                'response' => \is_array($resultContent) ? $resultContent : [
                    'rawResponse' => $resultContent,
                ],
            ]),
        ]];
    }

    protected function supportedDataClass(): string
    {
        return ToolCallMessage::class;
    }

    protected function supportsModel(BaseModel $model): bool
    {
        return $model instanceof Model;
    }
}
