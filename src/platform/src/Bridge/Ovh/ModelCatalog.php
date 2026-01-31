<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ovh;

use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * @author Loïc Sapone <loic@sapone.fr>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: string, capabilities: list<Capability>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        $defaultModels = [
            'qwen3guard-gen-8b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'qwen3guard-gen-0.6b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'qwen3-coder-30b-a3b-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
                ],
            ],
            'gpt-oss-20b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
                ],
            ],
            'gpt-oss-120b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
                ],
            ],
            'qwen3-32b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
                ],
            ],
            'mistral-small-3.2-24b-instruct-2506' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
                ],
            ],
            'qwen2.5-vl-72b-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'llama-3.1-8b-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
                ],
            ],
            'mistral-7b-instruct-v0.3' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
                ],
            ],
            'meta-llama-3_3-70b-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
                ],
            ],
            'mistral-nemo-instruct-2407' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'bge-multilingual-gemma2' => [
                'class' => EmbeddingsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'bge-m3' => [
                'class' => EmbeddingsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
        ];

        $this->models = array_merge($defaultModels, $additionalModels);
    }
}
