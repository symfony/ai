<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\AiMlApi;

use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
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
            // Completion models (GPT variants)
            'gpt-3.5-turbo' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT-3.5 Turbo',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'gpt-3.5-turbo-0125' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT-3.5 Turbo 0125',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'gpt-3.5-turbo-1106' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT-3.5 Turbo 1106',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'gpt-4o' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT-4o',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'gpt-4o-2024-08-06' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT-4o 2024-08-06',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'gpt-4o-2024-05-13' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT-4o 2024-05-13',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'gpt-4o-mini' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT-4o Mini',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'gpt-4o-mini-2024-07-18' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT-4o Mini 2024-07-18',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'gpt-4-turbo' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT-4 Turbo',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'gpt-4' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT-4',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'gpt-4-turbo-2024-04-09' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT-4 Turbo 2024-04-09',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'gpt-4-0125-preview' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT-4 0125 Preview',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'gpt-4-1106-preview' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT-4 1106 Preview',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'chatgpt-4o-latest' => [
                'class' => CompletionsModel::class,
                'label' => 'ChatGPT 4o Latest',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'gpt-4o-audio-preview' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT-4o Audio Preview',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'gpt-4o-mini-audio-preview' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT-4o Mini Audio Preview',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'gpt-4o-search-preview' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT-4o Search Preview',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'gpt-4o-mini-search-preview' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT-4o Mini Search Preview',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'o1-mini' => [
                'class' => CompletionsModel::class,
                'label' => 'O1 Mini',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'o1-mini-2024-09-12' => [
                'class' => CompletionsModel::class,
                'label' => 'O1 Mini 2024-09-12',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'o1' => [
                'class' => CompletionsModel::class,
                'label' => 'O1',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'o3-mini' => [
                'class' => CompletionsModel::class,
                'label' => 'O3 Mini',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            // OpenAI future models
            'openai/o3-2025-04-16' => [
                'class' => CompletionsModel::class,
                'label' => 'OpenAI O3 2025-04-16',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'openai/o3-pro' => [
                'class' => CompletionsModel::class,
                'label' => 'OpenAI O3 Pro',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'openai/gpt-4.1-2025-04-14' => [
                'class' => CompletionsModel::class,
                'label' => 'OpenAI GPT-4.1 2025-04-14',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'openai/gpt-4.1-mini-2025-04-14' => [
                'class' => CompletionsModel::class,
                'label' => 'OpenAI GPT-4.1 Mini 2025-04-14',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'openai/gpt-4.1-nano-2025-04-14' => [
                'class' => CompletionsModel::class,
                'label' => 'OpenAI GPT-4.1 Nano 2025-04-14',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'openai/o4-mini-2025-04-16' => [
                'class' => CompletionsModel::class,
                'label' => 'OpenAI O4 Mini 2025-04-16',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'openai/gpt-oss-20b' => [
                'class' => CompletionsModel::class,
                'label' => 'OpenAI GPT-OSS 20B',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'openai/gpt-oss-120b' => [
                'class' => CompletionsModel::class,
                'label' => 'OpenAI GPT-OSS 120B',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'openai/gpt-5-2025-08-07' => [
                'class' => CompletionsModel::class,
                'label' => 'OpenAI GPT-5 2025-08-07',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'openai/gpt-5-mini-2025-08-07' => [
                'class' => CompletionsModel::class,
                'label' => 'OpenAI GPT-5 Mini 2025-08-07',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'openai/gpt-5-nano-2025-08-07' => [
                'class' => CompletionsModel::class,
                'label' => 'OpenAI GPT-5 Nano 2025-08-07',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'openai/gpt-5-chat-latest' => [
                'class' => CompletionsModel::class,
                'label' => 'OpenAI GPT-5 Chat Latest',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            // DeepSeek models
            'deepseek-chat' => [
                'class' => CompletionsModel::class,
                'label' => 'DeepSeek Chat',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'deepseek/deepseek-chat' => [
                'class' => CompletionsModel::class,
                'label' => 'DeepSeek Chat',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'deepseek/deepseek-chat-v3-0324' => [
                'class' => CompletionsModel::class,
                'label' => 'DeepSeek Chat v3 0324',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'deepseek/deepseek-r1' => [
                'class' => CompletionsModel::class,
                'label' => 'DeepSeek R1',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek-reasoner' => [
                'class' => CompletionsModel::class,
                'label' => 'DeepSeek Reasoner',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek/deepseek-prover-v2' => [
                'class' => CompletionsModel::class,
                'label' => 'DeepSeek Prover v2',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek/deepseek-chat-v3.1' => [
                'class' => CompletionsModel::class,
                'label' => 'DeepSeek Chat v3.1',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'deepseek/deepseek-reasoner-v3.1' => [
                'class' => CompletionsModel::class,
                'label' => 'DeepSeek Reasoner v3.1',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            // Qwen models
            'Qwen/Qwen2-72B-Instruct' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen 2 72B Instruct',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'qwen-max' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen Max',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'qwen-plus' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen Plus',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'qwen-turbo' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen Turbo',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'qwen-max-2025-01-25' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen Max 2025-01-25',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'Qwen/Qwen2.5-72B-Instruct-Turbo' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen 2.5 72B Instruct Turbo',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'Qwen/QwQ-32B' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen QwQ 32B',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'Qwen/Qwen3-235B-A22B-fp8-tput' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen 3 235B A22B',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'alibaba/qwen3-32b' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen 3 32B',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'alibaba/qwen3-coder-480b-a35b-instruct' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen 3 Coder 480B A35B',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'alibaba/qwen3-235b-a22b-thinking-2507' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen 3 235B A22B Thinking',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'Qwen/Qwen2.5-7B-Instruct-Turbo' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen 2.5 7B Instruct Turbo',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'Qwen/Qwen2.5-Coder-32B-Instruct' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen 2.5 Coder 32B Instruct',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            // Mistral models
            'mistralai/Mixtral-8x7B-Instruct-v0.1' => [
                'class' => CompletionsModel::class,
                'label' => 'Mixtral 8x7B Instruct v0.1',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'mistralai/Mistral-7B-Instruct-v0.2' => [
                'class' => CompletionsModel::class,
                'label' => 'Mistral 7B Instruct v0.2',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'mistralai/Mistral-7B-Instruct-v0.1' => [
                'class' => CompletionsModel::class,
                'label' => 'Mistral 7B Instruct v0.1',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'mistralai/Mistral-7B-Instruct-v0.3' => [
                'class' => CompletionsModel::class,
                'label' => 'Mistral 7B Instruct v0.3',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'mistralai/mistral-tiny' => [
                'class' => CompletionsModel::class,
                'label' => 'Mistral Tiny',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'mistralai/mistral-nemo' => [
                'class' => CompletionsModel::class,
                'label' => 'Mistral Nemo',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'mistralai/codestral-2501' => [
                'class' => CompletionsModel::class,
                'label' => 'Codestral 2501',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            // Meta Llama models
            'meta-llama/Llama-3.3-70B-Instruct-Turbo' => [
                'class' => CompletionsModel::class,
                'label' => 'Llama 3.3 70B Instruct Turbo',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'meta-llama/Llama-3.2-3B-Instruct-Turbo' => [
                'class' => CompletionsModel::class,
                'label' => 'Llama 3.2 3B Instruct Turbo',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'meta-llama/Meta-Llama-3-8B-Instruct-Lite' => [
                'class' => CompletionsModel::class,
                'label' => 'Llama 3 8B Instruct Lite',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'meta-llama/Llama-3-70b-chat-hf' => [
                'class' => CompletionsModel::class,
                'label' => 'Llama 3 70B Chat',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'meta-llama/Meta-Llama-3.1-405B-Instruct-Turbo' => [
                'class' => CompletionsModel::class,
                'label' => 'Llama 3.1 405B Instruct Turbo',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'meta-llama/Meta-Llama-3.1-8B-Instruct-Turbo' => [
                'class' => CompletionsModel::class,
                'label' => 'Llama 3.1 8B Instruct Turbo',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'meta-llama/Meta-Llama-3.1-70B-Instruct-Turbo' => [
                'class' => CompletionsModel::class,
                'label' => 'Llama 3.1 70B Instruct Turbo',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'meta-llama/llama-4-scout' => [
                'class' => CompletionsModel::class,
                'label' => 'Llama 4 Scout',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'meta-llama/llama-4-maverick' => [
                'class' => CompletionsModel::class,
                'label' => 'Llama 4 Maverick',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            // Claude models
            'claude-3-opus-20240229' => [
                'class' => CompletionsModel::class,
                'label' => 'Claude 3 Opus',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'claude-3-haiku-20240307' => [
                'class' => CompletionsModel::class,
                'label' => 'Claude 3 Haiku',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'claude-3-5-sonnet-20240620' => [
                'class' => CompletionsModel::class,
                'label' => 'Claude 3.5 Sonnet',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'claude-3-5-sonnet-20241022' => [
                'class' => CompletionsModel::class,
                'label' => 'Claude 3.5 Sonnet 20241022',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'claude-3-5-haiku-20241022' => [
                'class' => CompletionsModel::class,
                'label' => 'Claude 3.5 Haiku 20241022',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'claude-3-7-sonnet-20250219' => [
                'class' => CompletionsModel::class,
                'label' => 'Claude 3.7 Sonnet',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'anthropic/claude-opus-4' => [
                'class' => CompletionsModel::class,
                'label' => 'Claude Opus 4',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'anthropic/claude-sonnet-4' => [
                'class' => CompletionsModel::class,
                'label' => 'Claude Sonnet 4',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'anthropic/claude-opus-4.1' => [
                'class' => CompletionsModel::class,
                'label' => 'Claude Opus 4.1',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'claude-opus-4-1' => [
                'class' => CompletionsModel::class,
                'label' => 'Claude Opus 4.1',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'claude-opus-4-1-20250805' => [
                'class' => CompletionsModel::class,
                'label' => 'Claude Opus 4.1 20250805',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                ],
            ],
            // Gemini models
            'gemini-2.0-flash-exp' => [
                'class' => CompletionsModel::class,
                'label' => 'Gemini 2.0 Flash Exp',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'gemini-2.0-flash' => [
                'class' => CompletionsModel::class,
                'label' => 'Gemini 2.0 Flash',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'gemini-2.5-flash' => [
                'class' => CompletionsModel::class,
                'label' => 'Gemini 2.5 Flash',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'google/gemini-2.5-flash-lite-preview' => [
                'class' => CompletionsModel::class,
                'label' => 'Gemini 2.5 Flash Lite Preview',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'google/gemini-2.5-flash' => [
                'class' => CompletionsModel::class,
                'label' => 'Gemini 2.5 Flash',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'google/gemini-2.5-pro' => [
                'class' => CompletionsModel::class,
                'label' => 'Gemini 2.5 Pro',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'google/gemma-2-27b-it' => [
                'class' => CompletionsModel::class,
                'label' => 'Gemma 2 27B Instruct',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'google/gemma-3-4b-it' => [
                'class' => CompletionsModel::class,
                'label' => 'Gemma 3 4B Instruct',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'google/gemma-3-12b-it' => [
                'class' => CompletionsModel::class,
                'label' => 'Gemma 3 12B Instruct',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'google/gemma-3-27b-it' => [
                'class' => CompletionsModel::class,
                'label' => 'Gemma 3 27B Instruct',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'google/gemma-3n-e4b-it' => [
                'class' => CompletionsModel::class,
                'label' => 'Gemma 3 Nano E4B Instruct',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            // X.AI models
            'x-ai/grok-3-beta' => [
                'class' => CompletionsModel::class,
                'label' => 'Grok 3 Beta',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'x-ai/grok-3-mini-beta' => [
                'class' => CompletionsModel::class,
                'label' => 'Grok 3 Mini Beta',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'x-ai/grok-4-07-09' => [
                'class' => CompletionsModel::class,
                'label' => 'Grok 4 07-09',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            // Other models
            'anthracite-org/magnum-v4-72b' => [
                'class' => CompletionsModel::class,
                'label' => 'Magnum v4 72B',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'nvidia/llama-3.1-nemotron-70b-instruct' => [
                'class' => CompletionsModel::class,
                'label' => 'Nvidia Llama 3.1 Nemotron 70B Instruct',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'cohere/command-r-plus' => [
                'class' => CompletionsModel::class,
                'label' => 'Cohere Command R+',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'cohere/command-a' => [
                'class' => CompletionsModel::class,
                'label' => 'Cohere Command A',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'MiniMax-Text-01' => [
                'class' => CompletionsModel::class,
                'label' => 'MiniMax Text 01',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'minimax/m1' => [
                'class' => CompletionsModel::class,
                'label' => 'MiniMax M1',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'moonshot/kimi-k2-preview' => [
                'class' => CompletionsModel::class,
                'label' => 'Kimi K2 Preview',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'perplexity/sonar' => [
                'class' => CompletionsModel::class,
                'label' => 'Perplexity Sonar',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'perplexity/sonar-pro' => [
                'class' => CompletionsModel::class,
                'label' => 'Perplexity Sonar Pro',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'zhipu/glm-4.5-air' => [
                'class' => CompletionsModel::class,
                'label' => 'GLM 4.5 Air',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'zhipu/glm-4.5' => [
                'class' => CompletionsModel::class,
                'label' => 'GLM 4.5',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            // Embedding models
            'text-embedding-3-small' => [
                'class' => EmbeddingsModel::class,
                'label' => 'Text Embedding 3 Small (Embeddings)',
                'capabilities' => [Capability::INPUT_MULTIPLE, Capability::EMBEDDINGS],
            ],
            'text-embedding-3-large' => [
                'class' => EmbeddingsModel::class,
                'label' => 'Text Embedding 3 Large (Embeddings)',
                'capabilities' => [Capability::INPUT_MULTIPLE, Capability::EMBEDDINGS],
            ],
            'text-embedding-ada-002' => [
                'class' => EmbeddingsModel::class,
                'label' => 'Text Embedding Ada 002 (Embeddings)',
                'capabilities' => [Capability::INPUT_MULTIPLE, Capability::EMBEDDINGS],
            ],
            'togethercomputer/m2-bert-80M-32k-retrieval' => [
                'class' => EmbeddingsModel::class,
                'label' => 'M2-BERT 80M 32K Retrieval (Embeddings)',
                'capabilities' => [Capability::INPUT_MULTIPLE, Capability::EMBEDDINGS],
            ],
            'BAAI/bge-base-en-v1.5' => [
                'class' => EmbeddingsModel::class,
                'label' => 'BGE Base EN v1.5 (Embeddings)',
                'capabilities' => [Capability::INPUT_MULTIPLE, Capability::EMBEDDINGS],
            ],
            'BAAI/bge-large-en-v1.' => [
                'class' => EmbeddingsModel::class,
                'label' => 'BGE Large EN v1.5 (Embeddings)',
                'capabilities' => [Capability::INPUT_MULTIPLE, Capability::EMBEDDINGS],
            ],
            'voyage-large-2-instruct' => [
                'class' => EmbeddingsModel::class,
                'label' => 'Voyage Large 2 Instruct (Embeddings)',
                'capabilities' => [Capability::INPUT_MULTIPLE, Capability::EMBEDDINGS],
            ],
            'voyage-finance-2' => [
                'class' => EmbeddingsModel::class,
                'label' => 'Voyage Finance 2 (Embeddings)',
                'capabilities' => [Capability::INPUT_MULTIPLE, Capability::EMBEDDINGS],
            ],
            'voyage-multilingual-2' => [
                'class' => EmbeddingsModel::class,
                'label' => 'Voyage Multilingual 2 (Embeddings)',
                'capabilities' => [Capability::INPUT_MULTIPLE, Capability::EMBEDDINGS],
            ],
            'voyage-law-2' => [
                'class' => EmbeddingsModel::class,
                'label' => 'Voyage Law 2 (Embeddings)',
                'capabilities' => [Capability::INPUT_MULTIPLE, Capability::EMBEDDINGS],
            ],
            'voyage-code-2' => [
                'class' => EmbeddingsModel::class,
                'label' => 'Voyage Code 2 (Embeddings)',
                'capabilities' => [Capability::INPUT_MULTIPLE, Capability::EMBEDDINGS],
            ],
            'voyage-large-2' => [
                'class' => EmbeddingsModel::class,
                'label' => 'Voyage Large 2 (Embeddings)',
                'capabilities' => [Capability::INPUT_MULTIPLE, Capability::EMBEDDINGS],
            ],
            'voyage-2' => [
                'class' => EmbeddingsModel::class,
                'label' => 'Voyage 2 (Embeddings)',
                'capabilities' => [Capability::INPUT_MULTIPLE, Capability::EMBEDDINGS],
            ],
            'textembedding-gecko@003' => [
                'class' => EmbeddingsModel::class,
                'label' => 'Text Embedding Gecko 003 (Embeddings)',
                'capabilities' => [Capability::INPUT_MULTIPLE, Capability::EMBEDDINGS],
            ],
            'textembedding-gecko-multilingual@001' => [
                'class' => EmbeddingsModel::class,
                'label' => 'Text Embedding Gecko Multilingual 001 (Embeddings)',
                'capabilities' => [Capability::INPUT_MULTIPLE, Capability::EMBEDDINGS],
            ],
            'text-multilingual-embedding-002' => [
                'class' => EmbeddingsModel::class,
                'label' => 'Text Multilingual Embedding 002 (Embeddings)',
                'capabilities' => [Capability::INPUT_MULTIPLE, Capability::EMBEDDINGS],
            ],
        ];

        $this->models = array_merge($defaultModels, $additionalModels);
    }
}
