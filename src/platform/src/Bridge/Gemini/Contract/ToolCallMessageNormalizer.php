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
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Model;

/**
 * @author Valtteri R <valtzu@gmail.com>
 */
final class ToolCallMessageNormalizer extends ModelContractNormalizer
{
    /**
     * @param ToolCallMessage $data
     *
     * @return array{
     *      functionResponse: array{
     *          id?: string,
     *          name: string,
     *          response: array{result: array<int|string, mixed>|string}
     *      }
     *  }[]
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $resultContent = json_validate($data->getContent())
            ? json_decode($data->getContent(), true) : $data->getContent();

        // Gemini's API requires `response` (FunctionResponse) to be a Protobuf Struct
        $functionResponse = [
            'name' => $data->getToolCall()->getName(),
            'response' => ['result' => $resultContent],
        ];

        // Gemini < 3.0 may return an empty string as the ID which is invalid
        $id = $data->getToolCall()->getId();
        if ('' !== $id) {
            $functionResponse['id'] = $id;
        }

        return [[
            'functionResponse' => $functionResponse,
        ]];
    }

    protected function supportedDataClass(): string
    {
        return ToolCallMessage::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Gemini;
    }
}
