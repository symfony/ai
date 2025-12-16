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
use Symfony\AI\Platform\Message\Content\File;
use Symfony\AI\Platform\Message\Content\Text;
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
     *          id: string,
     *          name: string,
     *          response: array<int|string, mixed>
     *      }
     *  }[]
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $response = $this->buildResponse($data);

        return [[
            'functionResponse' => array_filter([
                'id' => $data->getToolCall()->getId(),
                'name' => $data->getToolCall()->getName(),
                'response' => $response,
            ]),
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

    /**
     * @return array<int|string, mixed>
     */
    private function buildResponse(ToolCallMessage $data): array
    {
        $contents = $data->getContent();

        // Check if we have multimodal content
        $hasMultimodal = false;
        foreach ($contents as $content) {
            if ($content instanceof File) {
                $hasMultimodal = true;
                break;
            }
        }

        if (!$hasMultimodal) {
            // Text-only: use the original JSON parsing logic
            $textContent = $data->asText() ?? '';
            $resultContent = json_validate($textContent)
                ? json_decode($textContent, true) : $textContent;

            return \is_array($resultContent) ? $resultContent : [
                'rawResponse' => $resultContent,
            ];
        }

        // Multimodal content: build parts array
        $parts = [];
        foreach ($contents as $content) {
            if ($content instanceof Text) {
                $parts[] = ['text' => $content->getText()];
            } elseif ($content instanceof File) {
                $parts[] = [
                    'inline_data' => [
                        'mime_type' => $content->getFormat(),
                        'data' => $content->asBase64(),
                    ],
                ];
            }
        }

        return ['parts' => $parts];
    }
}
