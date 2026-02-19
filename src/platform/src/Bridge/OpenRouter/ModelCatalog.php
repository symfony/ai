<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter;

use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelCatalog extends AbstractOpenRouterModelCatalog
{
    /**
     * @param array<string, array{class: string, label?: string, capabilities: list<Capability>}> $additionalModels
     */
    public function __construct(
        array $additionalModels = [],
    ) {
        parent::__construct();

        // OpenRouter provides access to many different models from various providers
        // The model list is changed avery few days. This list is generated at 2025-11-21.
        // This catalog only contains the current state of the model list as default models
        // For a full and up-2-date list of models incl. all capabilities, use the ModelApiCatalog
        $defaultModels = [
            // Models
            'x-ai/grok-4.1-fast' => [
                'class' => CompletionsModel::class,
                'label' => 'Grok 4.1 Fast',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemini-3-pro-preview' => [
                'class' => CompletionsModel::class,
                'label' => 'Gemini 3 Pro Preview',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepcogito/cogito-v2.1-671b' => [
                'class' => CompletionsModel::class,
                'label' => 'Cogito V2.1 671B',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-5.1' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT 5.1',
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-5.1-chat' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT 5.1 Chat',
                'capabilities' => [
                    Capability::INPUT_PDF,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-5.1-codex' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT 5.1 Codex',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-5.1-codex-mini' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT 5.1 Codex Mini',
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'kwaipilot/kat-coder-pro:free' => [
                'class' => CompletionsModel::class,
                'label' => 'Kat Coder Pro Free',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'moonshotai/kimi-linear-48b-a3b-instruct' => [
                'class' => CompletionsModel::class,
                'label' => 'Kimi Linear 48B A3B Instruct',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'moonshotai/kimi-k2-thinking' => [
                'class' => CompletionsModel::class,
                'label' => 'Kimi K2 Thinking',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'amazon/nova-premier-v1' => [
                'class' => CompletionsModel::class,
                'label' => 'Nova Premier V1',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'perplexity/sonar-pro-search' => [
                'class' => CompletionsModel::class,
                'label' => 'Sonar Pro Search',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/voxtral-small-24b-2507' => [
                'class' => CompletionsModel::class,
                'label' => 'Voxtral Small 24B 2507',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_AUDIO,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-oss-safeguard-20b' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT OSS Safeguard 20B',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'nvidia/nemotron-nano-12b-v2-vl:free' => [
                'class' => CompletionsModel::class,
                'label' => 'Nemotron Nano 12B V2 VL Free',
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'nvidia/nemotron-nano-12b-v2-vl' => [
                'class' => CompletionsModel::class,
                'label' => 'Nemotron Nano 12B V2 VL',
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'minimax/minimax-m2' => [
                'class' => CompletionsModel::class,
                'label' => 'Minimax M2',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'liquid/lfm2-8b-a1b' => [
                'class' => CompletionsModel::class,
                'label' => 'LFM2 8B A1B',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'liquid/lfm-2.2-6b' => [
                'class' => CompletionsModel::class,
                'label' => 'LFM 2.2 6B',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'ibm-granite/granite-4.0-h-micro' => [
                'class' => CompletionsModel::class,
                'label' => 'Granite 4.0 H Micro',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepcogito/cogito-v2-preview-llama-405b' => [
                'class' => CompletionsModel::class,
                'label' => 'Cogito V2 Preview Llama 405B',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-5-image-mini' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT 5 Image Mini',
                'capabilities' => [
                    Capability::INPUT_PDF,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'anthropic/claude-haiku-4.5' => [
                'class' => CompletionsModel::class,
                'label' => 'Claude Haiku 4.5',
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-vl-8b-thinking' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen3 VL 8B Thinking',
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-vl-8b-instruct' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen3 VL 8B Instruct',
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-5-image' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT 5 Image',
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/o3-deep-research' => [
                'class' => CompletionsModel::class,
                'label' => 'O3 Deep Research',
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/o4-mini-deep-research' => [
                'class' => CompletionsModel::class,
                'label' => 'O4 Mini Deep Research',
                'capabilities' => [
                    Capability::INPUT_PDF,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'nvidia/llama-3.3-nemotron-super-49b-v1.5' => [
                'class' => CompletionsModel::class,
                'label' => 'Llama 3.3 Nemotron Super 49B V1.5',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'baidu/ernie-4.5-21b-a3b-thinking' => [
                'class' => CompletionsModel::class,
                'label' => 'Ernie 4.5 21B A3B Thinking',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemini-2.5-flash-image' => [
                'class' => CompletionsModel::class,
                'label' => 'Gemini 2.5 Flash Image',
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-vl-30b-a3b-thinking' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen3 VL 30B A3B Thinking',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-vl-30b-a3b-instruct' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen3 VL 30B A3B Instruct',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-5-pro' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT 5 Pro',
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'z-ai/glm-4.6' => [
                'class' => CompletionsModel::class,
                'label' => 'GLM 4.6',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'z-ai/glm-4.6:exacto' => [
                'class' => CompletionsModel::class,
                'label' => 'GLM 4.6 Exacto',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'anthropic/claude-sonnet-4.5' => [
                'class' => CompletionsModel::class,
                'label' => 'Claude Sonnet 4.5',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek/deepseek-v3.2-exp' => [
                'class' => CompletionsModel::class,
                'label' => 'DeepSeek V3.2 Exp',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'thedrummer/cydonia-24b-v4.1' => [
                'class' => CompletionsModel::class,
                'label' => 'Cydonia 24B V4.1',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'relace/relace-apply-3' => [
                'class' => CompletionsModel::class,
                'label' => 'Relace Apply 3',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemini-2.5-flash-preview-09-2025' => [
                'class' => CompletionsModel::class,
                'label' => 'Gemini 2.5 Flash Preview 09 2025',
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemini-2.5-flash-lite-preview-09-2025' => [
                'class' => CompletionsModel::class,
                'label' => 'Gemini 2.5 Flash Lite Preview 09 2025',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-vl-235b-a22b-thinking' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen3 VL 235B A22B Thinking',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-vl-235b-a22b-instruct' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen3 VL 235B A22B Instruct',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-max' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen3 Max',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-coder-plus' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen3 Coder Plus',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-5-codex' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT 5 Codex',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek/deepseek-v3.1-terminus' => [
                'class' => CompletionsModel::class,
                'label' => 'DeepSeek V3.1 Terminus',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek/deepseek-v3.1-terminus:exacto' => [
                'class' => CompletionsModel::class,
                'label' => 'DeepSeek V3.1 Terminus Exacto',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'x-ai/grok-4-fast' => [
                'class' => CompletionsModel::class,
                'label' => 'Grok 4 Fast',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'alibaba/tongyi-deepresearch-30b-a3b:free' => [
                'class' => CompletionsModel::class,
                'label' => 'Tongyi Deepresearch 30B A3B Free',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'alibaba/tongyi-deepresearch-30b-a3b' => [
                'class' => CompletionsModel::class,
                'label' => 'Tongyi Deepresearch 30B A3B',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-coder-flash' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen3 Coder Flash',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'arcee-ai/afm-4.5b' => [
                'class' => CompletionsModel::class,
                'label' => 'AFM 4.5B',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'opengvlab/internvl3-78b' => [
                'class' => CompletionsModel::class,
                'label' => 'InternVL3 78B',
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-next-80b-a3b-thinking' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen3 Next 80B A3B Thinking',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-next-80b-a3b-instruct' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen3 Next 80B A3B Instruct',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meituan/longcat-flash-chat:free' => [
                'class' => CompletionsModel::class,
                'label' => 'Longcat Flash Chat Free',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meituan/longcat-flash-chat' => [
                'class' => CompletionsModel::class,
                'label' => 'Longcat Flash Chat',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen-plus-2025-07-28' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen Plus 2025 07 28',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen-plus-2025-07-28:thinking' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen Plus 2025 07 28 Thinking',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'nvidia/nemotron-nano-9b-v2:free' => [
                'class' => CompletionsModel::class,
                'label' => 'Nemotron Nano 9B V2 Free',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'nvidia/nemotron-nano-9b-v2' => [
                'class' => CompletionsModel::class,
                'label' => 'Nemotron Nano 9B V2',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'moonshotai/kimi-k2-0905' => [
                'class' => CompletionsModel::class,
                'label' => 'Kimi K2 0905',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'moonshotai/kimi-k2-0905:exacto' => [
                'class' => CompletionsModel::class,
                'label' => 'Kimi K2 0905 Exacto',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepcogito/cogito-v2-preview-llama-70b' => [
                'class' => CompletionsModel::class,
                'label' => 'Cogito V2 Preview Llama 70B',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepcogito/cogito-v2-preview-llama-109b-moe' => [
                'class' => CompletionsModel::class,
                'label' => 'Cogito V2 Preview Llama 109B MOE',
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepcogito/cogito-v2-preview-deepseek-671b' => [
                'class' => CompletionsModel::class,
                'label' => 'Cogito V2 Preview DeepSeek 671B',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'stepfun-ai/step3' => [
                'class' => CompletionsModel::class,
                'label' => 'Step 3',
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-30b-a3b-thinking-2507' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen3 30B A3B Thinking 2507',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'x-ai/grok-code-fast-1' => [
                'class' => CompletionsModel::class,
                'label' => 'Grok Code Fast 1',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'nousresearch/hermes-4-70b' => [
                'class' => CompletionsModel::class,
                'label' => 'Hermes 4 70B',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'nousresearch/hermes-4-405b' => [
                'class' => CompletionsModel::class,
                'label' => 'Hermes 4 405B',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemini-2.5-flash-image-preview' => [
                'class' => CompletionsModel::class,
                'label' => 'Gemini 2.5 Flash Image Preview',
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek/deepseek-chat-v3.1' => [
                'class' => CompletionsModel::class,
                'label' => 'DeepSeek Chat V3.1',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-audio' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT Audio',
                'capabilities' => [
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_AUDIO,
                ],
            ],
            'openai/gpt-audio-mini' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT Audio Mini',
                'capabilities' => [
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_AUDIO,
                ],
            ],
            'openai/gpt-4o-audio-preview' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT 4O Audio Preview',
                'capabilities' => [
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_AUDIO,
                ],
            ],
            'mistralai/mistral-medium-3.1' => [
                'class' => CompletionsModel::class,
                'label' => 'Mistral Medium 3.1',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'baidu/ernie-4.5-21b-a3b' => [
                'class' => CompletionsModel::class,
                'label' => 'Ernie 4.5 21B A3B',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'baidu/ernie-4.5-vl-28b-a3b' => [
                'class' => CompletionsModel::class,
                'label' => 'Ernie 4.5 VL 28B A3B',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'z-ai/glm-4.5v' => [
                'class' => CompletionsModel::class,
                'label' => 'GLM 4.5V',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'ai21/jamba-mini-1.7' => [
                'class' => CompletionsModel::class,
                'label' => 'Jamba Mini 1.7',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'ai21/jamba-large-1.7' => [
                'class' => CompletionsModel::class,
                'label' => 'Jamba Large 1.7',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-5-chat' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT 5 Chat',
                'capabilities' => [
                    Capability::INPUT_PDF,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-5' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT 5',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-5-mini' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT 5 Mini',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-5-nano' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT 5 Nano',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-oss-120b' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT OSS 120B',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-oss-120b:exacto' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT OSS 120B Exacto',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-oss-20b:free' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT OSS 20B Free',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-oss-20b' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT OSS 20B',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'anthropic/claude-opus-4.1' => [
                'class' => CompletionsModel::class,
                'label' => 'Claude Opus 4.1',
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/codestral-2508' => [
                'class' => CompletionsModel::class,
                'label' => 'Codestral 2508',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-coder-30b-a3b-instruct' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen3 Coder 30B A3B Instruct',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-30b-a3b-instruct-2507' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen3 30B A3B Instruct 2507',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'z-ai/glm-4.5' => [
                'class' => CompletionsModel::class,
                'label' => 'GLM 4.5',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'z-ai/glm-4.5-air:free' => [
                'class' => CompletionsModel::class,
                'label' => 'GLM 4.5 Air Free',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'z-ai/glm-4.5-air' => [
                'class' => CompletionsModel::class,
                'label' => 'GLM 4.5 Air',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-235b-a22b-thinking-2507' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen3 235B A22B Thinking 2507',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'z-ai/glm-4-32b' => [
                'class' => CompletionsModel::class,
                'label' => 'GLM 4 32B',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-coder:free' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen3 Coder Free',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-coder' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen3 Coder',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-coder:exacto' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen3 Coder Exacto',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'bytedance/ui-tars-1.5-7b' => [
                'class' => CompletionsModel::class,
                'label' => 'UI TARS 1.5 7B',
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemini-2.5-flash-lite' => [
                'class' => CompletionsModel::class,
                'label' => 'Gemini 2.5 Flash Lite',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-235b-a22b-2507' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen3 235B A22B 2507',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'switchpoint/router' => [
                'class' => CompletionsModel::class,
                'label' => 'Switchpoint Router',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'moonshotai/kimi-k2:free' => [
                'class' => CompletionsModel::class,
                'label' => 'Kimi K2 Free',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'moonshotai/kimi-k2' => [
                'class' => CompletionsModel::class,
                'label' => 'Kimi K2',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'thudm/glm-4.1v-9b-thinking' => [
                'class' => CompletionsModel::class,
                'label' => 'GLM 4.1V 9B Thinking',
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/devstral-medium' => [
                'class' => CompletionsModel::class,
                'label' => 'Devstral Medium',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/devstral-small' => [
                'class' => CompletionsModel::class,
                'label' => 'Devstral Small',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'cognitivecomputations/dolphin-mistral-24b-venice-edition:free' => [
                'class' => CompletionsModel::class,
                'label' => 'Dolphin Mistral 24B Venice Edition Free',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'x-ai/grok-4' => [
                'class' => CompletionsModel::class,
                'label' => 'Grok 4',
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemma-3n-e2b-it:free' => [
                'class' => CompletionsModel::class,
                'label' => 'Gemma 3N E2B IT Free',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'tencent/hunyuan-a13b-instruct' => [
                'class' => CompletionsModel::class,
                'label' => 'Hunyuan A13B Instruct',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'tngtech/deepseek-r1t2-chimera:free' => [
                'class' => CompletionsModel::class,
                'label' => 'DeepSeek R1T2 Chimera Free',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'tngtech/deepseek-r1t2-chimera' => [
                'class' => CompletionsModel::class,
                'label' => 'DeepSeek R1T2 Chimera',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'morph/morph-v3-large' => [
                'class' => CompletionsModel::class,
                'label' => 'Morph V3 Large',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'morph/morph-v3-fast' => [
                'class' => CompletionsModel::class,
                'label' => 'Morph V3 Fast',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'baidu/ernie-4.5-vl-424b-a47b' => [
                'class' => CompletionsModel::class,
                'label' => 'Ernie 4.5 VL 424B A47B',
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'baidu/ernie-4.5-300b-a47b' => [
                'class' => CompletionsModel::class,
                'label' => 'Ernie 4.5 300B A47B',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'thedrummer/anubis-70b-v1.1' => [
                'class' => CompletionsModel::class,
                'label' => 'Anubis 70B V1.1',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'inception/mercury' => [
                'class' => CompletionsModel::class,
                'label' => 'Mercury',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-small-3.2-24b-instruct:free' => [
                'class' => CompletionsModel::class,
                'label' => 'Mistral Small 3.2 24B Instruct Free',
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-small-3.2-24b-instruct' => [
                'class' => CompletionsModel::class,
                'label' => 'Mistral Small 3.2 24B Instruct',
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'minimax/minimax-m1' => [
                'class' => CompletionsModel::class,
                'label' => 'Minimax M1',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemini-2.5-flash' => [
                'class' => CompletionsModel::class,
                'label' => 'Gemini 2.5 Flash',
                'capabilities' => [
                    Capability::INPUT_PDF,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemini-2.5-pro' => [
                'class' => CompletionsModel::class,
                'label' => 'Gemini 2.5 Pro',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'moonshotai/kimi-dev-72b' => [
                'class' => CompletionsModel::class,
                'label' => 'Kimi Dev 72B',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/o3-pro' => [
                'class' => CompletionsModel::class,
                'label' => 'O3 Pro',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'x-ai/grok-3-mini' => [
                'class' => CompletionsModel::class,
                'label' => 'Grok 3 Mini',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'x-ai/grok-3' => [
                'class' => CompletionsModel::class,
                'label' => 'Grok 3',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/magistral-small-2506' => [
                'class' => CompletionsModel::class,
                'label' => 'Magistral Small 2506',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/magistral-medium-2506:thinking' => [
                'class' => CompletionsModel::class,
                'label' => 'Magistral Medium 2506 Thinking',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/magistral-medium-2506' => [
                'class' => CompletionsModel::class,
                'label' => 'Magistral Medium 2506',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemini-2.5-pro-preview' => [
                'class' => CompletionsModel::class,
                'label' => 'Gemini 2.5 Pro Preview',
                'capabilities' => [
                    Capability::INPUT_PDF,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_AUDIO,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek/deepseek-r1-0528-qwen3-8b:free' => [
                'class' => CompletionsModel::class,
                'label' => 'DeepSeek R1 0528 Qwen3 8B Free',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek/deepseek-r1-0528-qwen3-8b' => [
                'class' => CompletionsModel::class,
                'label' => 'DeepSeek R1 0528 Qwen3 8B',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek/deepseek-r1-0528:free' => [
                'class' => CompletionsModel::class,
                'label' => 'DeepSeek R1 0528 Free',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek/deepseek-r1-0528' => [
                'class' => CompletionsModel::class,
                'label' => 'DeepSeek R1 0528',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'anthropic/claude-opus-4' => [
                'class' => CompletionsModel::class,
                'label' => 'Claude Opus 4',
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'anthropic/claude-sonnet-4' => [
                'class' => CompletionsModel::class,
                'label' => 'Claude Sonnet 4',
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/devstral-small-2505' => [
                'class' => CompletionsModel::class,
                'label' => 'Devstral Small 2505',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemma-3n-e4b-it:free' => [
                'class' => CompletionsModel::class,
                'label' => 'Gemma 3N E4B IT Free',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemma-3n-e4b-it' => [
                'class' => CompletionsModel::class,
                'label' => 'Gemma 3N E4B IT',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/codex-mini' => [
                'class' => CompletionsModel::class,
                'label' => 'Codex Mini',
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'nousresearch/deephermes-3-mistral-24b-preview' => [
                'class' => CompletionsModel::class,
                'label' => 'Deephermes 3 Mistral 24B Preview',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-medium-3' => [
                'class' => CompletionsModel::class,
                'label' => 'Mistral Medium 3',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemini-2.5-pro-preview-05-06' => [
                'class' => CompletionsModel::class,
                'label' => 'Gemini 2.5 Pro Preview 05 06',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'arcee-ai/spotlight' => [
                'class' => CompletionsModel::class,
                'label' => 'Spotlight',
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'arcee-ai/maestro-reasoning' => [
                'class' => CompletionsModel::class,
                'label' => 'Maestro Reasoning',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'arcee-ai/virtuoso-large' => [
                'class' => CompletionsModel::class,
                'label' => 'Virtuoso Large',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'arcee-ai/coder-large' => [
                'class' => CompletionsModel::class,
                'label' => 'Coder Large',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'microsoft/phi-4-reasoning-plus' => [
                'class' => CompletionsModel::class,
                'label' => 'Phi 4 Reasoning Plus',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'inception/mercury-coder' => [
                'class' => CompletionsModel::class,
                'label' => 'Mercury Coder',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-4b:free' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen3 4B Free',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek/deepseek-prover-v2' => [
                'class' => CompletionsModel::class,
                'label' => 'DeepSeek Prover V2',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meta-llama/llama-guard-4-12b' => [
                'class' => CompletionsModel::class,
                'label' => 'Llama Guard 4 12B',
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-30b-a3b:free' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen3 30B A3B Free',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-30b-a3b' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen3 30B A3B',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-8b' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen3 8B',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-14b:free' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen3 14B Free',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-14b' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen3 14B',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-32b' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen3 32B',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-235b-a22b:free' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen3 235B A22B Free',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-235b-a22b' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen3 235B A22B',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'tngtech/deepseek-r1t-chimera:free' => [
                'class' => CompletionsModel::class,
                'label' => 'DeepSeek R1T Chimera Free',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'tngtech/deepseek-r1t-chimera' => [
                'class' => CompletionsModel::class,
                'label' => 'DeepSeek R1T Chimera',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'microsoft/mai-ds-r1:free' => [
                'class' => CompletionsModel::class,
                'label' => 'MAI DS R1 Free',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'microsoft/mai-ds-r1' => [
                'class' => CompletionsModel::class,
                'label' => 'MAI DS R1',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/o4-mini-high' => [
                'class' => CompletionsModel::class,
                'label' => 'O4 Mini High',
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/o3' => [
                'class' => CompletionsModel::class,
                'label' => 'O3',
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/o4-mini' => [
                'class' => CompletionsModel::class,
                'label' => 'O4 Mini',
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen2.5-coder-7b-instruct' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen 2.5 Coder 7B Instruct',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-4.1' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT 4.1',
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-4.1-mini' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT 4.1 Mini',
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-4.1-nano' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT 4.1 Nano',
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'eleutherai/llemma_7b' => [
                'class' => CompletionsModel::class,
                'label' => 'LLeMMA 7B',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'alfredpros/codellama-7b-instruct-solidity' => [
                'class' => CompletionsModel::class,
                'label' => 'Codellama 7B Instruct Solidity',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'arliai/qwq-32b-arliai-rpr-v1:free' => [
                'class' => CompletionsModel::class,
                'label' => 'QwQ 32B Arliai RPR V1 Free',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'arliai/qwq-32b-arliai-rpr-v1' => [
                'class' => CompletionsModel::class,
                'label' => 'QwQ 32B Arliai RPR V1',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'x-ai/grok-3-mini-beta' => [
                'class' => CompletionsModel::class,
                'label' => 'Grok 3 Mini Beta',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'x-ai/grok-3-beta' => [
                'class' => CompletionsModel::class,
                'label' => 'Grok 3 Beta',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'nvidia/llama-3.1-nemotron-ultra-253b-v1' => [
                'class' => CompletionsModel::class,
                'label' => 'Llama 3.1 Nemotron Ultra 253B V1',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meta-llama/llama-4-maverick' => [
                'class' => CompletionsModel::class,
                'label' => 'Llama 4 Maverick',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meta-llama/llama-4-scout' => [
                'class' => CompletionsModel::class,
                'label' => 'Llama 4 Scout',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen2.5-vl-32b-instruct:free' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen 2.5 VL 32B Instruct Free',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen2.5-vl-32b-instruct' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen 2.5 VL 32B Instruct',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek/deepseek-chat-v3-0324:free' => [
                'class' => CompletionsModel::class,
                'label' => 'DeepSeek Chat V3 0324 Free',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek/deepseek-chat-v3-0324' => [
                'class' => CompletionsModel::class,
                'label' => 'DeepSeek Chat V3 0324',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/o1-pro' => [
                'class' => CompletionsModel::class,
                'label' => 'O1 Pro',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-small-3.1-24b-instruct:free' => [
                'class' => CompletionsModel::class,
                'label' => 'Mistral Small 3.1 24B Instruct Free',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-small-3.1-24b-instruct' => [
                'class' => CompletionsModel::class,
                'label' => 'Mistral Small 3.1 24B Instruct',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'allenai/olmo-2-0325-32b-instruct' => [
                'class' => CompletionsModel::class,
                'label' => 'OLMo 2 0325 32B Instruct',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemma-3-4b-it:free' => [
                'class' => CompletionsModel::class,
                'label' => 'Gemma 3 4B IT Free',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemma-3-4b-it' => [
                'class' => CompletionsModel::class,
                'label' => 'Gemma 3 4B IT',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemma-3-12b-it:free' => [
                'class' => CompletionsModel::class,
                'label' => 'Gemma 3 12B IT Free',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemma-3-12b-it' => [
                'class' => CompletionsModel::class,
                'label' => 'Gemma 3 12B IT',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'cohere/command-a' => [
                'class' => CompletionsModel::class,
                'label' => 'Command A',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-4o-mini-search-preview' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT 4O Mini Search Preview',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-4o-search-preview' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT 4O Search Preview',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemma-3-27b-it:free' => [
                'class' => CompletionsModel::class,
                'label' => 'Gemma 3 27B IT Free',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemma-3-27b-it' => [
                'class' => CompletionsModel::class,
                'label' => 'Gemma 3 27B IT',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'thedrummer/skyfall-36b-v2' => [
                'class' => CompletionsModel::class,
                'label' => 'Skyfall 36B V2',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'microsoft/phi-4-multimodal-instruct' => [
                'class' => CompletionsModel::class,
                'label' => 'Phi 4 Multimodal Instruct',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'perplexity/sonar-reasoning-pro' => [
                'class' => CompletionsModel::class,
                'label' => 'Sonar Reasoning Pro',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'perplexity/sonar-pro' => [
                'class' => CompletionsModel::class,
                'label' => 'Sonar Pro',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'perplexity/sonar-deep-research' => [
                'class' => CompletionsModel::class,
                'label' => 'Sonar Deep Research',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwq-32b' => [
                'class' => CompletionsModel::class,
                'label' => 'QwQ 32B',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemini-2.0-flash-lite-001' => [
                'class' => CompletionsModel::class,
                'label' => 'Gemini 2.0 Flash Lite 001',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'anthropic/claude-3.7-sonnet:thinking' => [
                'class' => CompletionsModel::class,
                'label' => 'Claude 3.7 Sonnet Thinking',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'anthropic/claude-3.7-sonnet' => [
                'class' => CompletionsModel::class,
                'label' => 'Claude 3.7 Sonnet',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-saba' => [
                'class' => CompletionsModel::class,
                'label' => 'Mistral Saba',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meta-llama/llama-guard-3-8b' => [
                'class' => CompletionsModel::class,
                'label' => 'Llama Guard 3 8B',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/o3-mini-high' => [
                'class' => CompletionsModel::class,
                'label' => 'O3 Mini High',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemini-2.0-flash-001' => [
                'class' => CompletionsModel::class,
                'label' => 'Gemini 2.0 Flash 001',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen-vl-plus' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen VL Plus',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'aion-labs/aion-1.0' => [
                'class' => CompletionsModel::class,
                'label' => 'Aion 1.0',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'aion-labs/aion-1.0-mini' => [
                'class' => CompletionsModel::class,
                'label' => 'Aion 1.0 Mini',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'aion-labs/aion-rp-llama-3.1-8b' => [
                'class' => CompletionsModel::class,
                'label' => 'Aion RP Llama 3.1 8B',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen-vl-max' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen VL Max',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen-turbo' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen Turbo',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen2.5-vl-72b-instruct' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen 2.5 VL 72B Instruct',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen-plus' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen Plus',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen-max' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen Max',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/o3-mini' => [
                'class' => CompletionsModel::class,
                'label' => 'O3 Mini',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-small-24b-instruct-2501:free' => [
                'class' => CompletionsModel::class,
                'label' => 'Mistral Small 24B Instruct 2501 Free',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-small-24b-instruct-2501' => [
                'class' => CompletionsModel::class,
                'label' => 'Mistral Small 24B Instruct 2501',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek/deepseek-r1-distill-qwen-32b' => [
                'class' => CompletionsModel::class,
                'label' => 'DeepSeek R1 Distill Qwen 32B',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek/deepseek-r1-distill-qwen-14b' => [
                'class' => CompletionsModel::class,
                'label' => 'DeepSeek R1 Distill Qwen 14B',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'perplexity/sonar-reasoning' => [
                'class' => CompletionsModel::class,
                'label' => 'Sonar Reasoning',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'perplexity/sonar' => [
                'class' => CompletionsModel::class,
                'label' => 'Sonar',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek/deepseek-r1-distill-llama-70b:free' => [
                'class' => CompletionsModel::class,
                'label' => 'DeepSeek R1 Distill Llama 70B Free',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek/deepseek-r1-distill-llama-70b' => [
                'class' => CompletionsModel::class,
                'label' => 'DeepSeek R1 Distill Llama 70B',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek/deepseek-r1:free' => [
                'class' => CompletionsModel::class,
                'label' => 'DeepSeek R1 Free',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek/deepseek-r1' => [
                'class' => CompletionsModel::class,
                'label' => 'DeepSeek R1',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'minimax/minimax-01' => [
                'class' => CompletionsModel::class,
                'label' => 'Minimax 01',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/codestral-2501' => [
                'class' => CompletionsModel::class,
                'label' => 'Codestral 2501',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'microsoft/phi-4' => [
                'class' => CompletionsModel::class,
                'label' => 'Phi 4',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'sao10k/l3.1-70b-hanami-x1' => [
                'class' => CompletionsModel::class,
                'label' => 'L3.1 70B Hanami X1',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek/deepseek-chat' => [
                'class' => CompletionsModel::class,
                'label' => 'DeepSeek Chat',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'sao10k/l3.3-euryale-70b' => [
                'class' => CompletionsModel::class,
                'label' => 'L3.3 Euryale 70B',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/o1' => [
                'class' => CompletionsModel::class,
                'label' => 'O1',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'cohere/command-r7b-12-2024' => [
                'class' => CompletionsModel::class,
                'label' => 'Command R7B 12 2024',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemini-2.0-flash-exp:free' => [
                'class' => CompletionsModel::class,
                'label' => 'Gemini 2.0 Flash Exp Free',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meta-llama/llama-3.3-70b-instruct:free' => [
                'class' => CompletionsModel::class,
                'label' => 'Llama 3.3 70B Instruct Free',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meta-llama/llama-3.3-70b-instruct' => [
                'class' => CompletionsModel::class,
                'label' => 'Llama 3.3 70B Instruct',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'amazon/nova-lite-v1' => [
                'class' => CompletionsModel::class,
                'label' => 'Nova Lite V1',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'amazon/nova-micro-v1' => [
                'class' => CompletionsModel::class,
                'label' => 'Nova Micro V1',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'amazon/nova-pro-v1' => [
                'class' => CompletionsModel::class,
                'label' => 'Nova Pro V1',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-4o-2024-11-20' => [
                'class' => CompletionsModel::class,
                'label' => 'GPT 4O 2024 11 20',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-large-2411' => [
                'class' => CompletionsModel::class,
                'label' => 'Mistral Large 2411',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-large-2407' => [
                'class' => CompletionsModel::class,
                'label' => 'Mistral Large 2407',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/pixtral-large-2411' => [
                'class' => CompletionsModel::class,
                'label' => 'Pixtral Large 2411',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen-2.5-coder-32b-instruct:free' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen 2.5 Coder 32B Instruct Free',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen-2.5-coder-32b-instruct' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen 2.5 Coder 32B Instruct',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'raifle/sorcererlm-8x22b' => [
                'class' => CompletionsModel::class,
                'label' => 'Sorcererlm 8x22B',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'thedrummer/unslopnemo-12b' => [
                'class' => CompletionsModel::class,
                'label' => 'Unslopnemo 12B',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'anthropic/claude-3.5-haiku-20241022' => [
                'class' => CompletionsModel::class,
                'label' => 'Claude 3.5 Haiku 20241022',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'anthropic/claude-3.5-haiku' => [
                'class' => CompletionsModel::class,
                'label' => 'Claude 3.5 Haiku',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'anthracite-org/magnum-v4-72b' => [
                'class' => CompletionsModel::class,
                'label' => 'Magnum V4 72B',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'anthropic/claude-3.5-sonnet' => [
                'class' => CompletionsModel::class,
                'label' => 'Claude 3.5 Sonnet',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/ministral-3b' => [
                'class' => CompletionsModel::class,
                'label' => 'Ministral 3B',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/ministral-8b' => [
                'class' => CompletionsModel::class,
                'label' => 'Ministral 8B',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen-2.5-7b-instruct' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen 2.5 7B Instruct',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'nvidia/llama-3.1-nemotron-70b-instruct' => [
                'class' => CompletionsModel::class,
                'label' => 'Llama 3.1 Nemotron 70B Instruct',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'inflection/inflection-3-pi' => [
                'class' => CompletionsModel::class,
                'label' => 'Inflection 3 PI',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'inflection/inflection-3-productivity' => [
                'class' => CompletionsModel::class,
                'label' => 'Inflection 3 Productivity',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'thedrummer/rocinante-12b' => [
                'class' => CompletionsModel::class,
                'label' => 'Rocinante 12B',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meta-llama/llama-3.2-3b-instruct:free' => [
                'class' => CompletionsModel::class,
                'label' => 'Llama 3.2 3B Instruct Free',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meta-llama/llama-3.2-3b-instruct' => [
                'class' => CompletionsModel::class,
                'label' => 'Llama 3.2 3B Instruct',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meta-llama/llama-3.2-90b-vision-instruct' => [
                'class' => CompletionsModel::class,
                'label' => 'Llama 3.2 90B Vision Instruct',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meta-llama/llama-3.2-1b-instruct' => [
                'class' => CompletionsModel::class,
                'label' => 'Llama 3.2 1B Instruct',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meta-llama/llama-3.2-11b-vision-instruct' => [
                'class' => CompletionsModel::class,
                'label' => 'Llama 3.2 11B Vision Instruct',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen-2.5-72b-instruct:free' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen 2.5 72B Instruct Free',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen-2.5-72b-instruct' => [
                'class' => CompletionsModel::class,
                'label' => 'Qwen 2.5 72B Instruct',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'neversleep/llama-3.1-lumimaid-8b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/pixtral-12b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'cohere/command-r-08-2024' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'cohere/command-r-plus-08-2024' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen-2.5-vl-7b-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'sao10k/l3.1-euryale-70b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'microsoft/phi-3.5-mini-128k-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'nousresearch/hermes-3-llama-3.1-70b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'nousresearch/hermes-3-llama-3.1-405b:free' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'nousresearch/hermes-3-llama-3.1-405b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/chatgpt-4o-latest' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'sao10k/l3-lunaris-8b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-4o-2024-08-06' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meta-llama/llama-3.1-405b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meta-llama/llama-3.1-70b-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meta-llama/llama-3.1-405b-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meta-llama/llama-3.1-8b-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-nemo:free' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-nemo' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-4o-mini' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-4o-mini-2024-07-18' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemma-2-27b-it' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemma-2-9b-it' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'sao10k/l3-euryale-70b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-7b-instruct-v0.3' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'nousresearch/hermes-2-pro-llama-3-8b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-7b-instruct:free' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-7b-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'microsoft/phi-3-mini-128k-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'microsoft/phi-3-medium-128k-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-4o' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-4o:extended' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-4o-2024-05-13' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meta-llama/llama-guard-2-8b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meta-llama/llama-3-70b-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meta-llama/llama-3-8b-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mixtral-8x22b-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'microsoft/wizardlm-2-8x22b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-4-turbo' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'anthropic/claude-3-haiku' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'anthropic/claude-3-opus' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-large' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-4-turbo-preview' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-3.5-turbo-0613' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-small' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-tiny' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-7b-instruct-v0.2' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mixtral-8x7b-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'neversleep/noromaid-20b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'alpindale/goliath-120b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openrouter/auto' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-4-1106-preview' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-3.5-turbo-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-7b-instruct-v0.1' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-3.5-turbo-16k' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mancer/weaver' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'undi95/remm-slerp-l2-13b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'gryphe/mythomax-l2-13b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-4-0314' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-3.5-turbo' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-4' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],

            // Embeddings
            'thenlper/gte-base' => [
                'class' => EmbeddingsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'thenlper/gte-large' => [
                'class' => EmbeddingsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'intfloat/e5-large-v2' => [
                'class' => EmbeddingsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'intfloat/e5-base-v2' => [
                'class' => EmbeddingsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'intfloat/multilingual-e5-large' => [
                'class' => EmbeddingsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'sentence-transformers/paraphrase-minilm-l6-v2' => [
                'class' => EmbeddingsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'sentence-transformers/all-minilm-l12-v2' => [
                'class' => EmbeddingsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'baai/bge-base-en-v1.5' => [
                'class' => EmbeddingsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'sentence-transformers/multi-qa-mpnet-base-dot-v1' => [
                'class' => EmbeddingsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'baai/bge-large-en-v1.5' => [
                'class' => EmbeddingsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'baai/bge-m3' => [
                'class' => EmbeddingsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'sentence-transformers/all-mpnet-base-v2' => [
                'class' => EmbeddingsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'sentence-transformers/all-minilm-l6-v2' => [
                'class' => EmbeddingsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'mistralai/mistral-embed-2312' => [
                'class' => EmbeddingsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'google/gemini-embedding-001' => [
                'class' => EmbeddingsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'openai/text-embedding-ada-002' => [
                'class' => EmbeddingsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'mistralai/codestral-embed-2505' => [
                'class' => EmbeddingsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'openai/text-embedding-3-large' => [
                'class' => EmbeddingsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'openai/text-embedding-3-small' => [
                'class' => EmbeddingsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'qwen/qwen3-embedding-8b' => [
                'class' => EmbeddingsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'qwen/qwen3-embedding-4b' => [
                'class' => EmbeddingsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
        ];

        $this->models = [
            ...$this->models,
            ...$defaultModels,
            ...$additionalModels,
        ];
    }

    public function getModel(string $modelName): Model
    {
        if ('' === $modelName) {
            throw new InvalidArgumentException('Model name cannot be empty.');
        }

        $parsed = $this->parseModelName($modelName);
        $actualModelName = $parsed['name'];
        $catalogKey = $parsed['catalogKey'];
        $options = $parsed['options'];

        if (!isset($this->models[$catalogKey])) {
            // Add model to the list as default model
            $this->models[$catalogKey] = [
                'class' => CompletionsModel::class,
                'capabilities' => [],
            ];
        }

        $modelConfig = $this->models[$catalogKey];
        $modelClass = $modelConfig['class'];

        if (!class_exists($modelClass)) {
            throw new InvalidArgumentException(\sprintf('Model class "%s" does not exist.', $modelClass));
        }

        if (CompletionsModel::class === $modelClass && !\in_array(Capability::OUTPUT_STREAMING, $modelConfig['capabilities'])) {
            // Streaming is allowed for any model: https://openrouter.ai/docs/api/reference/streaming
            $modelConfig['capabilities'][] = Capability::OUTPUT_STREAMING;
        }

        $model = new $modelClass($actualModelName, $modelConfig['capabilities'], $options);
        if (!$model instanceof Model) {
            throw new InvalidArgumentException(\sprintf('Model class "%s" must extend "%s".', $modelClass, CompletionsModel::class));
        }

        return $model;
    }
}
