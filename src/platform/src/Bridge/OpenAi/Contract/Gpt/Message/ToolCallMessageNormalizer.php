<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Contract\Gpt\Message;

use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Message\Content\File;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\ImageUrl;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Model;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

/**
 * @author Pauline Vos <pauline.vos@mongodb.com>
 */
final class ToolCallMessageNormalizer extends ModelContractNormalizer
{
    use NormalizerAwareTrait;

    /**
     * @param ToolCallMessage $data
     *
     * @return array{
     *     type: 'function_call_output',
     *     call_id: string,
     *     output: string|list<array<string, mixed>>
     * }
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $contents = $data->getContent();

        if ($this->isTextOnly($contents)) {
            return [
                'type' => 'function_call_output',
                'call_id' => $data->getToolCall()->getId(),
                'output' => $data->asText() ?? '',
            ];
        }

        // Multimodal content: build output array with input_* types
        $output = [];
        foreach ($contents as $content) {
            if ($content instanceof Text) {
                $output[] = [
                    'type' => 'input_text',
                    'text' => $content->getText(),
                ];
            } elseif ($content instanceof Image) {
                $output[] = [
                    'type' => 'input_image',
                    'image_url' => $content->asDataUrl(),
                ];
            } elseif ($content instanceof ImageUrl) {
                $output[] = [
                    'type' => 'input_image',
                    'image_url' => $content->getUrl(),
                ];
            } elseif ($content instanceof File) {
                $output[] = [
                    'type' => 'input_file',
                    'file_data' => $content->asDataUrl(),
                ];
            }
        }

        return [
            'type' => 'function_call_output',
            'call_id' => $data->getToolCall()->getId(),
            'output' => $output,
        ];
    }

    protected function supportedDataClass(): string
    {
        return ToolCallMessage::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Gpt;
    }

    /**
     * @param array<mixed> $contents
     */
    private function isTextOnly(array $contents): bool
    {
        foreach ($contents as $content) {
            if (!$content instanceof Text) {
                return false;
            }
        }

        return true;
    }
}
