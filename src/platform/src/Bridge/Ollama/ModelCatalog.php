<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: class-string<Model>, capabilities: list<Capability>, label: string}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        $defaultModels = [
            'deepseek-r1' => [
                'class' => Ollama::class,
                'label' => 'DeepSeek R1',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
                ],
            ],
            'gpt-oss' => [
                'class' => Ollama::class,
                'label' => 'GPT-OSS',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
                ],
            ],
            'llama3.1' => [
                'class' => Ollama::class,
                'label' => 'Llama 3.1',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
                ],
            ],
            'llama3.2' => [
                'class' => Ollama::class,
                'label' => 'Llama 3.2',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
                ],
            ],
            'llama3' => [
                'class' => Ollama::class,
                'label' => 'Llama 3',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
                ],
            ],
            'mistral' => [
                'class' => Ollama::class,
                'label' => 'Mistral',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
                ],
            ],
            'qwen3' => [
                'class' => Ollama::class,
                'label' => 'Qwen 3',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
                ],
            ],
            'qwen' => [
                'class' => Ollama::class,
                'label' => 'Qwen',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
                ],
            ],
            'qwen2' => [
                'class' => Ollama::class,
                'label' => 'Qwen 2',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
                ],
            ],
            'qwen2.5' => [
                'class' => Ollama::class,
                'label' => 'Qwen 2.5',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
                ],
            ],
            'qwen2.5-coder' => [
                'class' => Ollama::class,
                'label' => 'Qwen 2.5 Coder',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
                ],
            ],
            'gemma3n' => [
                'class' => Ollama::class,
                'label' => 'Gemma 3 Nano',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'gemma3' => [
                'class' => Ollama::class,
                'label' => 'Gemma 3',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen2.5vl' => [
                'class' => Ollama::class,
                'label' => 'Qwen 2.5 VL',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'llava' => [
                'class' => Ollama::class,
                'label' => 'LLaVA',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'phi3' => [
                'class' => Ollama::class,
                'label' => 'Phi 3',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'gemma2' => [
                'class' => Ollama::class,
                'label' => 'Gemma 2',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'gemma' => [
                'class' => Ollama::class,
                'label' => 'Gemma',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'llama2' => [
                'class' => Ollama::class,
                'label' => 'Llama 2',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'nomic-embed-text' => [
                'class' => Ollama::class,
                'label' => 'Nomic Embed Text (Embeddings)',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_MULTIPLE,
                    Capability::EMBEDDINGS,
                ],
            ],
            'bge-m3' => [
                'class' => Ollama::class,
                'label' => 'BGE-M3 (Embeddings)',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_MULTIPLE,
                    Capability::EMBEDDINGS,
                ],
            ],
            'all-minilm' => [
                'class' => Ollama::class,
                'label' => 'All MiniLM (Embeddings)',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_MULTIPLE,
                    Capability::EMBEDDINGS,
                ],
            ],
        ];

        $this->models = [
            ...$defaultModels,
            ...$additionalModels,
        ];
    }
}
