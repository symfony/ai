<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\VertexAi;

use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Bridge\VertexAi\Embeddings\Model as EmbeddingsModel;
use Symfony\AI\Platform\Bridge\VertexAi\Gemini\Model as GeminiModel;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * @see https://cloud.google.com/vertex-ai/generative-ai/docs/model-reference/inference for more details
 * @see https://cloud.google.com/vertex-ai/generative-ai/docs/model-reference/text-embeddings-api for various options
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: class-string, label: string, capabilities: list<string>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        $defaultModels = [
            // Gemini models
            'gemini-3-pro-preview' => [
                'class' => Gemini::class,
                'label' => 'Gemini 3 Pro Preview',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
                ],
            ],
            'gemini-2.5-pro' => [
                'class' => GeminiModel::class,
                'label' => 'Gemini 2.5 Pro',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
                ],
            ],
            'gemini-2.5-flash' => [
                'class' => GeminiModel::class,
                'label' => 'Gemini 2.5 Flash',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
                ],
            ],
            'gemini-2.0-flash' => [
                'class' => GeminiModel::class,
                'label' => 'Gemini 2.0 Flash',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'gemini-2.5-flash-lite' => [
                'class' => GeminiModel::class,
                'label' => 'Gemini 2.5 Flash Lite',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
                ],
            ],
            'gemini-2.0-flash-lite' => [
                'class' => GeminiModel::class,
                'label' => 'Gemini 2.0 Flash Lite',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            // Embeddings models
            'gemini-embedding-001' => [
                'class' => EmbeddingsModel::class,
                'label' => 'Gemini Embedding 001 (Embeddings)',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_MULTIPLE,
                    Capability::EMBEDDINGS,
                ],
            ],
            'text-embedding-005' => [
                'class' => EmbeddingsModel::class,
                'label' => 'Text Embedding 005 (Embeddings)',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_MULTIPLE,
                    Capability::EMBEDDINGS,
                ],
            ],
            'text-multilingual-embedding-002' => [
                'class' => EmbeddingsModel::class,
                'label' => 'Text Multilingual Embedding 002 (Embeddings)',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_MULTIPLE,
                    Capability::EMBEDDINGS,
                ],
            ],
        ];

        $this->models = array_merge($defaultModels, $additionalModels);
    }
}
