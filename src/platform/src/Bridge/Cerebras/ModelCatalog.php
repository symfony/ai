<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cerebras;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 *
 * @see https://inference-docs.cerebras.ai/api-reference/chat-completions for details like options
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: string, label: string, capabilities: list<Capability>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        $defaultModels = [
            'llama-4-scout-17b-16e-instruct' => [
                'class' => Model::class,
                'label' => 'Llama 4 Scout 17B 16E Instruct',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'llama3.1-8b' => [
                'class' => Model::class,
                'label' => 'Llama 3.1 8B',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'llama-3.3-70b' => [
                'class' => Model::class,
                'label' => 'Llama 3.3 70B',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'llama-4-maverick-17b-128e-instruct' => [
                'class' => Model::class,
                'label' => 'Llama 4 Maverick 17B 128E Instruct',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'qwen-3-32b' => [
                'class' => Model::class,
                'label' => 'Qwen 3 32B',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'qwen-3-235b-a22b-instruct-2507' => [
                'class' => Model::class,
                'label' => 'Qwen 3 235B A22B Instruct',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'qwen-3-235b-a22b-thinking-2507' => [
                'class' => Model::class,
                'label' => 'Qwen 3 235B A22B Thinking',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'qwen-3-coder-480b' => [
                'class' => Model::class,
                'label' => 'Qwen 3 Coder 480B',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'gpt-oss-120b' => [
                'class' => Model::class,
                'label' => 'GPT-OSS 120B',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'zai-glm-4.7' => [
                'class' => Model::class,
                'label' => 'ZAI GLM 4.7',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
        ];

        $this->models = array_merge($defaultModels, $additionalModels);
    }
}
