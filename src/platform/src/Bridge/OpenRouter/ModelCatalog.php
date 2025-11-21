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

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelCatalog extends AbstractOpenRouterModelCatalog
{
    /**
     * @param array<string, array{class: string, capabilities: list<Capability>}> $additionalModels
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
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemini-3-pro-preview' => [
                'class' => Model::class,
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
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-5.1' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-5.1-chat' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_PDF,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-5.1-codex' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-5.1-codex-mini' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'kwaipilot/kat-coder-pro:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'moonshotai/kimi-linear-48b-a3b-instruct' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'moonshotai/kimi-k2-thinking' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'amazon/nova-premier-v1' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'perplexity/sonar-pro-search' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/voxtral-small-24b-2507' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_AUDIO,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-oss-safeguard-20b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'nvidia/nemotron-nano-12b-v2-vl:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'nvidia/nemotron-nano-12b-v2-vl' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'minimax/minimax-m2' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'liquid/lfm2-8b-a1b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'liquid/lfm-2.2-6b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'ibm-granite/granite-4.0-h-micro' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepcogito/cogito-v2-preview-llama-405b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-5-image-mini' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_PDF,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'anthropic/claude-haiku-4.5' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-vl-8b-thinking' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-vl-8b-instruct' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-5-image' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/o3-deep-research' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/o4-mini-deep-research' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_PDF,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'nvidia/llama-3.3-nemotron-super-49b-v1.5' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'baidu/ernie-4.5-21b-a3b-thinking' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemini-2.5-flash-image' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-vl-30b-a3b-thinking' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-vl-30b-a3b-instruct' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-5-pro' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'z-ai/glm-4.6' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'z-ai/glm-4.6:exacto' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'anthropic/claude-sonnet-4.5' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek/deepseek-v3.2-exp' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'thedrummer/cydonia-24b-v4.1' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'relace/relace-apply-3' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemini-2.5-flash-preview-09-2025' => [
                'class' => Model::class,
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
                'class' => Model::class,
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
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-vl-235b-a22b-instruct' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-max' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-coder-plus' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-5-codex' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek/deepseek-v3.1-terminus' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek/deepseek-v3.1-terminus:exacto' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'x-ai/grok-4-fast' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'alibaba/tongyi-deepresearch-30b-a3b:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'alibaba/tongyi-deepresearch-30b-a3b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-coder-flash' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'arcee-ai/afm-4.5b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'opengvlab/internvl3-78b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-next-80b-a3b-thinking' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-next-80b-a3b-instruct' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meituan/longcat-flash-chat:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meituan/longcat-flash-chat' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen-plus-2025-07-28' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen-plus-2025-07-28:thinking' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'nvidia/nemotron-nano-9b-v2:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'nvidia/nemotron-nano-9b-v2' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'moonshotai/kimi-k2-0905' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'moonshotai/kimi-k2-0905:exacto' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepcogito/cogito-v2-preview-llama-70b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepcogito/cogito-v2-preview-llama-109b-moe' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepcogito/cogito-v2-preview-deepseek-671b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'stepfun-ai/step3' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-30b-a3b-thinking-2507' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'x-ai/grok-code-fast-1' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'nousresearch/hermes-4-70b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'nousresearch/hermes-4-405b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemini-2.5-flash-image-preview' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek/deepseek-chat-v3.1' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-4o-audio-preview' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-medium-3.1' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'baidu/ernie-4.5-21b-a3b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'baidu/ernie-4.5-vl-28b-a3b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'z-ai/glm-4.5v' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'ai21/jamba-mini-1.7' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'ai21/jamba-large-1.7' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-5-chat' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_PDF,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-5' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-5-mini' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-5-nano' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-oss-120b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-oss-120b:exacto' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-oss-20b:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-oss-20b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'anthropic/claude-opus-4.1' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/codestral-2508' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-coder-30b-a3b-instruct' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-30b-a3b-instruct-2507' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'z-ai/glm-4.5' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'z-ai/glm-4.5-air:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'z-ai/glm-4.5-air' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-235b-a22b-thinking-2507' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'z-ai/glm-4-32b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-coder:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-coder' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-coder:exacto' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'bytedance/ui-tars-1.5-7b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemini-2.5-flash-lite' => [
                'class' => Model::class,
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
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'switchpoint/router' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'moonshotai/kimi-k2:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'moonshotai/kimi-k2' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'thudm/glm-4.1v-9b-thinking' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/devstral-medium' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/devstral-small' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'cognitivecomputations/dolphin-mistral-24b-venice-edition:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'x-ai/grok-4' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemma-3n-e2b-it:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'tencent/hunyuan-a13b-instruct' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'tngtech/deepseek-r1t2-chimera:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'tngtech/deepseek-r1t2-chimera' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'morph/morph-v3-large' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'morph/morph-v3-fast' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'baidu/ernie-4.5-vl-424b-a47b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'baidu/ernie-4.5-300b-a47b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'thedrummer/anubis-70b-v1.1' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'inception/mercury' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-small-3.2-24b-instruct:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-small-3.2-24b-instruct' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'minimax/minimax-m1' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemini-2.5-flash' => [
                'class' => Model::class,
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
                'class' => Model::class,
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
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/o3-pro' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'x-ai/grok-3-mini' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'x-ai/grok-3' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/magistral-small-2506' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/magistral-medium-2506:thinking' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/magistral-medium-2506' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemini-2.5-pro-preview' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_PDF,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_AUDIO,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek/deepseek-r1-0528-qwen3-8b:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek/deepseek-r1-0528-qwen3-8b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek/deepseek-r1-0528:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek/deepseek-r1-0528' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'anthropic/claude-opus-4' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'anthropic/claude-sonnet-4' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/devstral-small-2505' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemma-3n-e4b-it:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemma-3n-e4b-it' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/codex-mini' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'nousresearch/deephermes-3-mistral-24b-preview' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-medium-3' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemini-2.5-pro-preview-05-06' => [
                'class' => Model::class,
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
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'arcee-ai/maestro-reasoning' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'arcee-ai/virtuoso-large' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'arcee-ai/coder-large' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'microsoft/phi-4-reasoning-plus' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'inception/mercury-coder' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-4b:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek/deepseek-prover-v2' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meta-llama/llama-guard-4-12b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-30b-a3b:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-30b-a3b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-8b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-14b:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-14b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-32b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-235b-a22b:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen3-235b-a22b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'tngtech/deepseek-r1t-chimera:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'tngtech/deepseek-r1t-chimera' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'microsoft/mai-ds-r1:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'microsoft/mai-ds-r1' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/o4-mini-high' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/o3' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/o4-mini' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen2.5-coder-7b-instruct' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-4.1' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-4.1-mini' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-4.1-nano' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'eleutherai/llemma_7b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'alfredpros/codellama-7b-instruct-solidity' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'arliai/qwq-32b-arliai-rpr-v1:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'arliai/qwq-32b-arliai-rpr-v1' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'x-ai/grok-3-mini-beta' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'x-ai/grok-3-beta' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'nvidia/llama-3.1-nemotron-ultra-253b-v1' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meta-llama/llama-4-maverick' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meta-llama/llama-4-scout' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen2.5-vl-32b-instruct:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen2.5-vl-32b-instruct' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek/deepseek-chat-v3-0324:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek/deepseek-chat-v3-0324' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/o1-pro' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-small-3.1-24b-instruct:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-small-3.1-24b-instruct' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'allenai/olmo-2-0325-32b-instruct' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemma-3-4b-it:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemma-3-4b-it' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemma-3-12b-it:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemma-3-12b-it' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'cohere/command-a' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-4o-mini-search-preview' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-4o-search-preview' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemma-3-27b-it:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemma-3-27b-it' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'thedrummer/skyfall-36b-v2' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'microsoft/phi-4-multimodal-instruct' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'perplexity/sonar-reasoning-pro' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'perplexity/sonar-pro' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'perplexity/sonar-deep-research' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwq-32b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemini-2.0-flash-lite-001' => [
                'class' => Model::class,
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
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'anthropic/claude-3.7-sonnet' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-saba' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meta-llama/llama-guard-3-8b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/o3-mini-high' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemini-2.0-flash-001' => [
                'class' => Model::class,
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
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'aion-labs/aion-1.0' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'aion-labs/aion-1.0-mini' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'aion-labs/aion-rp-llama-3.1-8b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen-vl-max' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen-turbo' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen2.5-vl-72b-instruct' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen-plus' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen-max' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/o3-mini' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-small-24b-instruct-2501:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-small-24b-instruct-2501' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek/deepseek-r1-distill-qwen-32b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek/deepseek-r1-distill-qwen-14b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'perplexity/sonar-reasoning' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'perplexity/sonar' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek/deepseek-r1-distill-llama-70b:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek/deepseek-r1-distill-llama-70b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek/deepseek-r1:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek/deepseek-r1' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'minimax/minimax-01' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/codestral-2501' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'microsoft/phi-4' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'sao10k/l3.1-70b-hanami-x1' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'deepseek/deepseek-chat' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'sao10k/l3.3-euryale-70b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/o1' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'cohere/command-r7b-12-2024' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemini-2.0-flash-exp:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meta-llama/llama-3.3-70b-instruct:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meta-llama/llama-3.3-70b-instruct' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'amazon/nova-lite-v1' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'amazon/nova-micro-v1' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'amazon/nova-pro-v1' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-4o-2024-11-20' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-large-2411' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-large-2407' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/pixtral-large-2411' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen-2.5-coder-32b-instruct:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen-2.5-coder-32b-instruct' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'raifle/sorcererlm-8x22b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'thedrummer/unslopnemo-12b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'anthropic/claude-3.5-haiku-20241022' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'anthropic/claude-3.5-haiku' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'anthracite-org/magnum-v4-72b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'anthropic/claude-3.5-sonnet' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/ministral-3b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/ministral-8b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen-2.5-7b-instruct' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'nvidia/llama-3.1-nemotron-70b-instruct' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'inflection/inflection-3-pi' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'inflection/inflection-3-productivity' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'thedrummer/rocinante-12b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meta-llama/llama-3.2-3b-instruct:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meta-llama/llama-3.2-3b-instruct' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meta-llama/llama-3.2-90b-vision-instruct' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meta-llama/llama-3.2-1b-instruct' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meta-llama/llama-3.2-11b-vision-instruct' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen-2.5-72b-instruct:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen-2.5-72b-instruct' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'neversleep/llama-3.1-lumimaid-8b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/pixtral-12b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'cohere/command-r-08-2024' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'cohere/command-r-plus-08-2024' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'qwen/qwen-2.5-vl-7b-instruct' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'sao10k/l3.1-euryale-70b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'microsoft/phi-3.5-mini-128k-instruct' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'nousresearch/hermes-3-llama-3.1-70b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'nousresearch/hermes-3-llama-3.1-405b:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'nousresearch/hermes-3-llama-3.1-405b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/chatgpt-4o-latest' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'sao10k/l3-lunaris-8b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-4o-2024-08-06' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meta-llama/llama-3.1-405b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meta-llama/llama-3.1-70b-instruct' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meta-llama/llama-3.1-405b-instruct' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meta-llama/llama-3.1-8b-instruct' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-nemo:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-nemo' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-4o-mini' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-4o-mini-2024-07-18' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemma-2-27b-it' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'google/gemma-2-9b-it' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'sao10k/l3-euryale-70b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-7b-instruct-v0.3' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'nousresearch/hermes-2-pro-llama-3-8b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-7b-instruct:free' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-7b-instruct' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'microsoft/phi-3-mini-128k-instruct' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'microsoft/phi-3-medium-128k-instruct' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-4o' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-4o:extended' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-4o-2024-05-13' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meta-llama/llama-guard-2-8b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meta-llama/llama-3-70b-instruct' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'meta-llama/llama-3-8b-instruct' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mixtral-8x22b-instruct' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'microsoft/wizardlm-2-8x22b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-4-turbo' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'anthropic/claude-3-haiku' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'anthropic/claude-3-opus' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-large' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-4-turbo-preview' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-3.5-turbo-0613' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-small' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-tiny' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-7b-instruct-v0.2' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mixtral-8x7b-instruct' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'neversleep/noromaid-20b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'alpindale/goliath-120b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openrouter/auto' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-4-1106-preview' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-3.5-turbo-instruct' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mistralai/mistral-7b-instruct-v0.1' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-3.5-turbo-16k' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'mancer/weaver' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'undi95/remm-slerp-l2-13b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'gryphe/mythomax-l2-13b' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-4-0314' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-3.5-turbo' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'openai/gpt-4' => [
                'class' => Model::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                ],
            ],

            // Embeddings
            'thenlper/gte-base' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'thenlper/gte-large' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'intfloat/e5-large-v2' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'intfloat/e5-base-v2' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'intfloat/multilingual-e5-large' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'sentence-transformers/paraphrase-minilm-l6-v2' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'sentence-transformers/all-minilm-l12-v2' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'baai/bge-base-en-v1.5' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'sentence-transformers/multi-qa-mpnet-base-dot-v1' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'baai/bge-large-en-v1.5' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'baai/bge-m3' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'sentence-transformers/all-mpnet-base-v2' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'sentence-transformers/all-minilm-l6-v2' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'mistralai/mistral-embed-2312' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'google/gemini-embedding-001' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'openai/text-embedding-ada-002' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'mistralai/codestral-embed-2505' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'openai/text-embedding-3-large' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'openai/text-embedding-3-small' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'qwen/qwen3-embedding-8b' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'qwen/qwen3-embedding-4b' => [
                'class' => Embeddings::class,
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
                'class' => Model::class,
                'capabilities' => [],
            ];
        }

        $modelConfig = $this->models[$catalogKey];
        $modelClass = $modelConfig['class'];

        if (!class_exists($modelClass)) {
            throw new InvalidArgumentException(\sprintf('Model class "%s" does not exist.', $modelClass));
        }

        $model = new $modelClass($actualModelName, $modelConfig['capabilities'], $options);
        if (!$model instanceof Model) {
            throw new InvalidArgumentException(\sprintf('Model class "%s" must extend "%s".', $modelClass, Model::class));
        }

        return $model;
    }
}
