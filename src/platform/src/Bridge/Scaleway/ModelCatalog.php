<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Scaleway;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: string, label: string, capabilities: list<Capability>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        $defaultModels = [
            'deepseek-r1-distill-llama-70b' => [
                'class' => Scaleway::class,
                'label' => 'DeepSeek R1 Distill Llama 70B',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'gemma-3-27b-it' => [
                'class' => Scaleway::class,
                'label' => 'Gemma 3 27B IT',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'llama-3.1-8b-instruct' => [
                'class' => Scaleway::class,
                'label' => 'Llama 3.1 8B Instruct',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'llama-3.3-70b-instruct' => [
                'class' => Scaleway::class,
                'label' => 'Llama 3.3 70B Instruct',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'devstral-small-2505' => [
                'class' => Scaleway::class,
                'label' => 'Devstral Small',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'mistral-nemo-instruct-2407' => [
                'class' => Scaleway::class,
                'label' => 'Mistral Nemo Instruct',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'pixtral-12b-2409' => [
                'class' => Scaleway::class,
                'label' => 'Pixtral 12B',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'mistral-small-3.2-24b-instruct-2506' => [
                'class' => Scaleway::class,
                'label' => 'Mistral Small 3.2 24B Instruct',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'gpt-oss-120b' => [
                'class' => Scaleway::class,
                'label' => 'GPT-OSS 120B',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen3-coder-30b-a3b-instruct' => [
                'class' => Scaleway::class,
                'label' => 'Qwen3 Coder 30B A3B Instruct',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen3-235b-a22b-instruct-2507' => [
                'class' => Scaleway::class,
                'label' => 'Qwen3 235B A22B Instruct',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'bge-multilingual-gemma2' => [
                'class' => Embeddings::class,
                'label' => 'BGE Multilingual Gemma2 (Embeddings)',
                'capabilities' => [Capability::INPUT_TEXT, Capability::EMBEDDINGS],
            ],
        ];

        $this->models = array_merge($defaultModels, $additionalModels);
    }
}
