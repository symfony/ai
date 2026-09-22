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
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;
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
     * @return array<array{
     *      functionResponse?: array{
     *          name: string,
     *          response: array{result: mixed}
     *      },
     *      inlineData?: array{mimeType: string, data: string}
     *  }>
     *
     * @throws \JsonException
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $text = $data->asText() ?? '';
        $resultContent = json_validate($text) ? json_decode($text, true, 512, \JSON_THROW_ON_ERROR) : $text;

        // Vertex AI requires `response` (FunctionResponse) to be a Protobuf Struct, which cannot hold a JSON list
        $parts = [[
            'functionResponse' => [
                'name' => $data->getToolCall()->getName(),
                'response' => ['result' => $resultContent],
            ],
        ]];

        foreach ($data->getContent() as $part) {
            if ($part instanceof Text) {
                continue;
            }

            if ($part instanceof Image) {
                $parts[] = ['inlineData' => [
                    'mimeType' => $part->getFormat(),
                    'data' => $part->asBase64(),
                ]];

                continue;
            }

            throw new RuntimeException(\sprintf('Unsupported tool result content part of type "%s".', get_debug_type($part)));
        }

        return $parts;
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
