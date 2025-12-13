<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Nova\Contract;

use Symfony\AI\Platform\Bridge\Bedrock\Nova\Nova;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Message\Content\File;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Model;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

use function Symfony\Component\String\u;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ToolCallMessageNormalizer extends ModelContractNormalizer implements NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    /**
     * @param ToolCallMessage $data
     *
     * @return array{
     *     role: 'user',
     *     content: array<array{
     *         toolResult: array{
     *             toolUseId: string,
     *             content: array<int, array{json?: string, image?: array{format: string, source: array{bytes: string}}}>,
     *         }
     *     }>
     * }
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $resultContent = $this->buildContent($data);

        return [
            'role' => 'user',
            'content' => [
                [
                    'toolResult' => [
                        'toolUseId' => $data->getToolCall()->getId(),
                        'content' => $resultContent,
                    ],
                ],
            ],
        ];
    }

    protected function supportedDataClass(): string
    {
        return ToolCallMessage::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Nova;
    }

    /**
     * @return array<int, array{json?: string, image?: array{format: string, source: array{bytes: string}}, document?: array{format: string, name: string, source: array{bytes: string}}}>
     */
    private function buildContent(ToolCallMessage $data): array
    {
        $contents = $data->getContent();

        // Check if we have only text content
        $hasMultimodal = false;
        foreach ($contents as $content) {
            if (!$content instanceof Text) {
                $hasMultimodal = true;
                break;
            }
        }

        if (!$hasMultimodal) {
            // Text-only: use JSON format
            return [['json' => $data->asText() ?? '']];
        }

        // Multimodal content: build content array
        $result = [];
        foreach ($contents as $content) {
            if ($content instanceof Text) {
                $result[] = ['json' => $content->getText()];
            } elseif ($content instanceof Image) {
                $result[] = [
                    'image' => [
                        'format' => u($content->getFormat())->replace('image/', '')->replace('jpg', 'jpeg')->toString(),
                        'source' => ['bytes' => $content->asBase64()],
                    ],
                ];
            } elseif ($content instanceof File) {
                // File includes Audio, PDF, and other binary types
                $format = u($content->getFormat())->after('/')->toString();
                $result[] = [
                    'document' => [
                        'format' => $format,
                        'name' => 'document',
                        'source' => ['bytes' => $content->asBase64()],
                    ],
                ];
            }
        }

        return $result;
    }
}
