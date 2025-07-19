<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Google\Contract;

use Symfony\AI\Platform\Bridge\Google\Gemini;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Model;

/**
 * @author Valtteri R <valtzu@gmail.com>
 */
final class ToolCallMessageNormalizer extends ModelContractNormalizer
{
    protected function supportedDataClass(): string
    {
        return ToolCallMessage::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Gemini;
    }

    /**
     * @param ToolCallMessage $data
     *
     * @return array{
     *      functionResponse: array{
     *          id: string,
     *          name: string,
     *          response: array<int|string, mixed>
     *      }
     *  }[]
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $resultContent = json_validate($data->content) ? json_decode($data->content, true) : $data->content;

        return [[
            'functionResponse' => array_filter([
                'id' => $data->toolCall->id,
                'name' => $data->toolCall->name,
                'response' => \is_array($resultContent) ? $resultContent : [
                    'rawResponse' => $resultContent, // Gemini expects the response to be an object, but not everyone uses objects as their responses.
                ],
            ]),
        ]];
    }
}
