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
 * Normalizes tool-call responses into Gemini's `functionResponse` shape.
 *
 * Gemini declares `functionResponse.response` as a `google.protobuf.Struct`,
 * which represents a JSON object. The tool result is JSON-decoded and then
 * coerced into Struct-compatible shape:
 *
 *  - associative array → passed through unchanged
 *  - list-array        → wrapped as `{items: [...]}` (Struct rejects sequential arrays)
 *  - scalar / null     → wrapped as `{rawResponse: <value>}`
 *
 * Without the list-array wrap, Gemini rejects the entire turn with
 * `Proto field is not repeating, cannot start list`.
 *
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 */
final class ToolCallMessageNormalizer extends ModelContractNormalizer
{
    /**
     * @param ToolCallMessage $data
     *
     * @return list<array{
     *     functionResponse: array{
     *         name: string,
     *         response: array<string, mixed>
     *     }
     * }>
     *
     * @throws \JsonException
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return [[
            'functionResponse' => [
                'name' => $data->getToolCall()->getName(),
                'response' => self::asStruct(self::decodeContent($data->getContent())),
            ],
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

    /**
     * @throws \JsonException
     */
    private static function decodeContent(string $content): mixed
    {
        return json_validate($content) ? json_decode($content, true, 512, \JSON_THROW_ON_ERROR) : $content;
    }

    /**
     * @return array<string, mixed>
     */
    private static function asStruct(mixed $value): array
    {
        if (!\is_array($value)) {
            return ['rawResponse' => $value];
        }

        if (array_is_list($value)) {
            return ['items' => $value];
        }

        return $value;
    }
}
