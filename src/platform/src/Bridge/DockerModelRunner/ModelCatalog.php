<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\DockerModelRunner;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: class-string, capabilities: list<string>, label: string}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        $defaultModels = [
            // Completions models
            'ai/gemma3n' => [
                'class' => Completions::class,
                'label' => 'Gemma 3 Nano',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'ai/gemma3' => [
                'class' => Completions::class,
                'label' => 'Gemma 3',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'ai/qwen2.5' => [
                'class' => Completions::class,
                'label' => 'Qwen 2.5',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'ai/qwen3' => [
                'class' => Completions::class,
                'label' => 'Qwen 3',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'ai/qwen3-coder' => [
                'class' => Completions::class,
                'label' => 'Qwen 3 Coder',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'ai/llama3.1' => [
                'class' => Completions::class,
                'label' => 'Llama 3.1',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'ai/llama3.2' => [
                'class' => Completions::class,
                'label' => 'Llama 3.2',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'ai/llama3.3' => [
                'class' => Completions::class,
                'label' => 'Llama 3.3',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'ai/mistral' => [
                'class' => Completions::class,
                'label' => 'Mistral',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'ai/mistral-nemo' => [
                'class' => Completions::class,
                'label' => 'Mistral Nemo',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'ai/phi4' => [
                'class' => Completions::class,
                'label' => 'Phi 4',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'ai/deepseek-r1-distill-llama' => [
                'class' => Completions::class,
                'label' => 'DeepSeek R1 Distill Llama',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'ai/seed-oss' => [
                'class' => Completions::class,
                'label' => 'Seed-OSS',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'ai/gpt-oss' => [
                'class' => Completions::class,
                'label' => 'GPT-OSS',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'ai/smollm2' => [
                'class' => Completions::class,
                'label' => 'SmolLM 2',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'ai/smollm3' => [
                'class' => Completions::class,
                'label' => 'SmolLM 3',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            // Embeddings models
            'ai/nomic-embed-text-v1.5' => [
                'class' => Embeddings::class,
                'label' => 'Nomic Embed Text v1.5 (Embeddings)',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'ai/mxbai-embed-large' => [
                'class' => Embeddings::class,
                'label' => 'MXBai Embed Large (Embeddings)',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'ai/embeddinggemma' => [
                'class' => Embeddings::class,
                'label' => 'Embedding Gemma (Embeddings)',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'ai/granite-embedding-multilingual' => [
                'class' => Embeddings::class,
                'label' => 'Granite Embedding Multilingual (Embeddings)',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
        ];

        $this->models = array_merge($defaultModels, $additionalModels);
    }
}
