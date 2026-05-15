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
     * @param array<string, array{class: string, capabilities: list<Capability>}> $additionalModels
     */
    public function __construct(
        array $additionalModels = [],
    ) {
        parent::__construct();

        // OpenRouter provides access to many different models from various providers
        // The model list is changed every few days. This list is generated at a static date.
        // This catalog only contains the current state of the model list as default models
        // For a full and up-2-date list of models incl. all capabilities, use the ModelApiCatalog

        // STATIC LIST START
        // Date: 2026-05-11T13:40:14+00:00
        $defaultModels = [
            'ai21/jamba-large-1.7' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'aion-labs/aion-1.0' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'aion-labs/aion-1.0-mini' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'aion-labs/aion-2.0' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'aion-labs/aion-rp-llama-3.1-8b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'alfredpros/codellama-7b-instruct-solidity' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'alibaba/tongyi-deepresearch-30b-a3b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'allenai/olmo-3-32b-think' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'alpindale/goliath-120b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'amazon/nova-2-lite-v1' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_MULTIMODAL,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'amazon/nova-lite-v1' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'amazon/nova-micro-v1' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'amazon/nova-premier-v1' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'amazon/nova-pro-v1' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'anthracite-org/magnum-v4-72b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'anthropic/claude-3-haiku' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'anthropic/claude-3.5-haiku' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'anthropic/claude-haiku-4.5' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'anthropic/claude-opus-4' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'anthropic/claude-opus-4.1' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'anthropic/claude-opus-4.5' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_PDF,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'anthropic/claude-opus-4.6' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'anthropic/claude-opus-4.6-fast' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'anthropic/claude-opus-4.7' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'anthropic/claude-sonnet-4' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'anthropic/claude-sonnet-4.5' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'anthropic/claude-sonnet-4.6' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'arcee-ai/coder-large' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'arcee-ai/maestro-reasoning' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'arcee-ai/spotlight' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'arcee-ai/trinity-large-preview' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'arcee-ai/trinity-large-thinking' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'arcee-ai/trinity-mini' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'arcee-ai/virtuoso-large' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'baai/bge-base-en-v1.5' => [
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
            'baidu/cobuddy:free' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'baidu/ernie-4.5-21b-a3b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'baidu/ernie-4.5-21b-a3b-thinking' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'baidu/ernie-4.5-300b-a47b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'baidu/ernie-4.5-vl-28b-a3b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'baidu/ernie-4.5-vl-424b-a47b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'baidu/qianfan-ocr-fast:free' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'bytedance-seed/seed-1.6' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'bytedance-seed/seed-1.6-flash' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'bytedance-seed/seed-2.0-lite' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'bytedance-seed/seed-2.0-mini' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'bytedance/ui-tars-1.5-7b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'cognitivecomputations/dolphin-mistral-24b-venice-edition:free' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'cohere/command-a' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'cohere/command-r-08-2024' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'cohere/command-r-plus-08-2024' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'cohere/command-r7b-12-2024' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'deepcogito/cogito-v2.1-671b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'deepseek/deepseek-chat' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'deepseek/deepseek-chat-v3-0324' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'deepseek/deepseek-chat-v3.1' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'deepseek/deepseek-r1' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'deepseek/deepseek-r1-0528' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'deepseek/deepseek-r1-distill-llama-70b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'deepseek/deepseek-r1-distill-qwen-32b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'deepseek/deepseek-v3.1-terminus' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'deepseek/deepseek-v3.2' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'deepseek/deepseek-v3.2-exp' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'deepseek/deepseek-v3.2-speciale' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'deepseek/deepseek-v4-flash' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'deepseek/deepseek-v4-pro' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'essentialai/rnj-1-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'google/gemini-2.0-flash-001' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'google/gemini-2.0-flash-lite-001' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'google/gemini-2.5-flash' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_PDF,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'google/gemini-2.5-flash-image' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'google/gemini-2.5-flash-lite' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'google/gemini-2.5-flash-lite-preview-09-2025' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'google/gemini-2.5-pro' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'google/gemini-2.5-pro-preview' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_PDF,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_AUDIO,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'google/gemini-2.5-pro-preview-05-06' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'google/gemini-3-flash-preview' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'google/gemini-3-pro-image-preview' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'google/gemini-3.1-flash-image-preview' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'google/gemini-3.1-flash-lite' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_MULTIMODAL,
                    Capability::INPUT_PDF,
                    Capability::INPUT_AUDIO,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'google/gemini-3.1-flash-lite-preview' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_MULTIMODAL,
                    Capability::INPUT_PDF,
                    Capability::INPUT_AUDIO,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'google/gemini-3.1-pro-preview' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_PDF,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'google/gemini-3.1-pro-preview-customtools' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_MULTIMODAL,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'google/gemini-embedding-001' => [
                'class' => EmbeddingsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'google/gemini-embedding-2-preview' => [
                'class' => EmbeddingsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'google/gemma-2-27b-it' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'google/gemma-3-12b-it' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'google/gemma-3-27b-it' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'google/gemma-3-4b-it' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'google/gemma-3n-e4b-it' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'google/gemma-4-26b-a4b-it' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'google/gemma-4-26b-a4b-it:free' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'google/gemma-4-31b-it' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'google/gemma-4-31b-it:free' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'google/lyria-3-clip-preview' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_AUDIO,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'google/lyria-3-pro-preview' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_AUDIO,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'gryphe/mythomax-l2-13b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'ibm-granite/granite-4.0-h-micro' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'ibm-granite/granite-4.1-8b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'inception/mercury-2' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'inclusionai/ling-2.6-1t' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'inclusionai/ling-2.6-flash' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'inclusionai/ring-2.6-1t:free' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'inflection/inflection-3-pi' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'inflection/inflection-3-productivity' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'intfloat/e5-base-v2' => [
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
            'intfloat/multilingual-e5-large' => [
                'class' => EmbeddingsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'kwaipilot/kat-coder-pro-v2' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'liquid/lfm-2-24b-a2b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'liquid/lfm-2.5-1.2b-instruct:free' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'liquid/lfm-2.5-1.2b-thinking:free' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'mancer/weaver' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'meta-llama/llama-3-70b-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'meta-llama/llama-3-8b-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'meta-llama/llama-3.1-70b-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'meta-llama/llama-3.1-8b-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'meta-llama/llama-3.2-11b-vision-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'meta-llama/llama-3.2-1b-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'meta-llama/llama-3.2-3b-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'meta-llama/llama-3.2-3b-instruct:free' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'meta-llama/llama-3.3-70b-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'meta-llama/llama-3.3-70b-instruct:free' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'meta-llama/llama-4-maverick' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'meta-llama/llama-4-scout' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'meta-llama/llama-guard-3-8b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'meta-llama/llama-guard-4-12b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'microsoft/phi-4' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'microsoft/phi-4-mini-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'microsoft/wizardlm-2-8x22b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'minimax/minimax-01' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'minimax/minimax-m1' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'minimax/minimax-m2' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'minimax/minimax-m2-her' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'minimax/minimax-m2.1' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'minimax/minimax-m2.5' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'minimax/minimax-m2.5:free' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'minimax/minimax-m2.7' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'mistralai/codestral-2508' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'mistralai/codestral-embed-2505' => [
                'class' => EmbeddingsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'mistralai/devstral-2512' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'mistralai/devstral-medium' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'mistralai/devstral-small' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'mistralai/ministral-14b-2512' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'mistralai/ministral-3b-2512' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'mistralai/ministral-8b-2512' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'mistralai/mistral-7b-instruct-v0.1' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'mistralai/mistral-embed-2312' => [
                'class' => EmbeddingsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'mistralai/mistral-large' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'mistralai/mistral-large-2407' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'mistralai/mistral-large-2411' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'mistralai/mistral-large-2512' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'mistralai/mistral-medium-3' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'mistralai/mistral-medium-3-5' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'mistralai/mistral-medium-3.1' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'mistralai/mistral-nemo' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'mistralai/mistral-saba' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'mistralai/mistral-small-24b-instruct-2501' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'mistralai/mistral-small-2603' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'mistralai/mistral-small-3.1-24b-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'mistralai/mistral-small-3.2-24b-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'mistralai/mixtral-8x22b-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'mistralai/pixtral-large-2411' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'mistralai/voxtral-small-24b-2507' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_AUDIO,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'moonshotai/kimi-k2' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'moonshotai/kimi-k2-0905' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'moonshotai/kimi-k2-thinking' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'moonshotai/kimi-k2.5' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'moonshotai/kimi-k2.6' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'morph/morph-v3-fast' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'morph/morph-v3-large' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'nex-agi/deepseek-v3.1-nex-n1' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'nousresearch/hermes-2-pro-llama-3-8b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'nousresearch/hermes-3-llama-3.1-405b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'nousresearch/hermes-3-llama-3.1-405b:free' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'nousresearch/hermes-3-llama-3.1-70b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'nousresearch/hermes-4-405b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'nousresearch/hermes-4-70b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'nvidia/llama-3.3-nemotron-super-49b-v1.5' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'nvidia/llama-nemotron-embed-vl-1b-v2:free' => [
                'class' => EmbeddingsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'nvidia/nemotron-3-nano-30b-a3b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'nvidia/nemotron-3-nano-30b-a3b:free' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'nvidia/nemotron-3-nano-omni-30b-a3b-reasoning:free' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'nvidia/nemotron-3-super-120b-a12b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'nvidia/nemotron-3-super-120b-a12b:free' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'nvidia/nemotron-nano-12b-v2-vl:free' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'nvidia/nemotron-nano-9b-v2' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'nvidia/nemotron-nano-9b-v2:free' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-3.5-turbo' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-3.5-turbo-0613' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-3.5-turbo-16k' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-3.5-turbo-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-4' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-4-0314' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-4-1106-preview' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-4-turbo' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-4-turbo-preview' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-4.1' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-4.1-mini' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-4.1-nano' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-4o' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-4o-2024-05-13' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-4o-2024-08-06' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-4o-2024-11-20' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-4o-audio-preview' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_AUDIO,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-4o-mini' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
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
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-4o-mini-search-preview' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-4o-search-preview' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-5' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-5-chat' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_PDF,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-5-codex' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-5-image' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-5-image-mini' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_PDF,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-5-mini' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-5-nano' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-5-pro' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-5.1' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-5.1-chat' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_PDF,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-5.1-codex' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-5.1-codex-max' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-5.1-codex-mini' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-5.2' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_PDF,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-5.2-chat' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_PDF,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-5.2-codex' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-5.2-pro' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-5.3-chat' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-5.3-codex' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-5.4' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-5.4-image-2' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-5.4-mini' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_PDF,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-5.4-nano' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_PDF,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-5.4-pro' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-5.5' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_PDF,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-5.5-pro' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_PDF,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-audio' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_AUDIO,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_AUDIO,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-audio-mini' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_AUDIO,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_AUDIO,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-chat-latest' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-oss-120b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-oss-120b:free' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'openai/gpt-oss-20b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-oss-20b:free' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'openai/gpt-oss-safeguard-20b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'openai/o1' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/o1-pro' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/o3' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/o3-deep-research' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/o3-mini' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/o3-mini-high' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/o3-pro' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/o4-mini' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/o4-mini-deep-research' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_PDF,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/o4-mini-high' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
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
            'openai/text-embedding-ada-002' => [
                'class' => EmbeddingsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'openrouter/owl-alpha' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openrouter/pareto-code' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'perplexity/pplx-embed-v1-0.6b' => [
                'class' => EmbeddingsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'perplexity/pplx-embed-v1-4b' => [
                'class' => EmbeddingsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'perplexity/sonar' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'perplexity/sonar-deep-research' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'perplexity/sonar-pro' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'perplexity/sonar-pro-search' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'perplexity/sonar-reasoning-pro' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'poolside/laguna-m.1:free' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'poolside/laguna-xs.2:free' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'prime-intellect/intellect-3' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen/qwen-2.5-72b-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen/qwen-2.5-7b-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'qwen/qwen-2.5-coder-32b-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'qwen/qwen-max' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'qwen/qwen-plus' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'qwen/qwen-plus-2025-07-28' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen/qwen-plus-2025-07-28:thinking' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen/qwen-turbo' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'qwen/qwen-vl-max' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'qwen/qwen-vl-plus' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'qwen/qwen2.5-vl-72b-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen/qwen3-14b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen/qwen3-235b-a22b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'qwen/qwen3-235b-a22b-2507' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen/qwen3-235b-a22b-thinking-2507' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen/qwen3-30b-a3b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen/qwen3-30b-a3b-instruct-2507' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen/qwen3-30b-a3b-thinking-2507' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen/qwen3-32b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen/qwen3-8b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen/qwen3-coder' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen/qwen3-coder-30b-a3b-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen/qwen3-coder-flash' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'qwen/qwen3-coder-next' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen/qwen3-coder-plus' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen/qwen3-coder:free' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'qwen/qwen3-embedding-4b' => [
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
            'qwen/qwen3-max' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'qwen/qwen3-max-thinking' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen/qwen3-next-80b-a3b-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen/qwen3-next-80b-a3b-instruct:free' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen/qwen3-next-80b-a3b-thinking' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen/qwen3-vl-235b-a22b-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen/qwen3-vl-235b-a22b-thinking' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'qwen/qwen3-vl-30b-a3b-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen/qwen3-vl-30b-a3b-thinking' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen/qwen3-vl-32b-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'qwen/qwen3-vl-8b-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen/qwen3-vl-8b-thinking' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen/qwen3.5-122b-a10b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen/qwen3.5-27b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen/qwen3.5-35b-a3b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen/qwen3.5-397b-a17b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen/qwen3.5-9b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen/qwen3.5-flash-02-23' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen/qwen3.5-plus-02-15' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen/qwen3.5-plus-20260420' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen/qwen3.6-27b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen/qwen3.6-35b-a3b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen/qwen3.6-flash' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen/qwen3.6-max-preview' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'qwen/qwen3.6-plus' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'rekaai/reka-edge' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'rekaai/reka-flash-3' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'relace/relace-apply-3' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'relace/relace-search' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'sao10k/l3-euryale-70b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'sao10k/l3-lunaris-8b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'sao10k/l3.1-70b-hanami-x1' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'sao10k/l3.1-euryale-70b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'sao10k/l3.3-euryale-70b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'sentence-transformers/all-minilm-l12-v2' => [
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
            'sentence-transformers/all-mpnet-base-v2' => [
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
            'sentence-transformers/paraphrase-minilm-l6-v2' => [
                'class' => EmbeddingsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'stepfun/step-3.5-flash' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'switchpoint/router' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'tencent/hunyuan-a13b-instruct' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'tencent/hy3-preview' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'thedrummer/cydonia-24b-v4.1' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'thedrummer/rocinante-12b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'thedrummer/skyfall-36b-v2' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'thedrummer/unslopnemo-12b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
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
            'undi95/remm-slerp-l2-13b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'upstage/solar-pro-3' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'writer/palmyra-x5' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'x-ai/grok-3' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'x-ai/grok-3-beta' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'x-ai/grok-3-mini' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'x-ai/grok-3-mini-beta' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'x-ai/grok-4' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'x-ai/grok-4-fast' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'x-ai/grok-4.1-fast' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'x-ai/grok-4.20' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'x-ai/grok-4.20-multi-agent' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'x-ai/grok-4.3' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'x-ai/grok-code-fast-1' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'xiaomi/mimo-v2-flash' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'xiaomi/mimo-v2-omni' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'xiaomi/mimo-v2-pro' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'xiaomi/mimo-v2.5' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'xiaomi/mimo-v2.5-pro' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'z-ai/glm-4-32b' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'z-ai/glm-4.5' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'z-ai/glm-4.5-air' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'z-ai/glm-4.5-air:free' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'z-ai/glm-4.5v' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'z-ai/glm-4.6' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'z-ai/glm-4.6v' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'z-ai/glm-4.7' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'z-ai/glm-4.7-flash' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'z-ai/glm-5' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'z-ai/glm-5-turbo' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            'z-ai/glm-5.1' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'z-ai/glm-5v-turbo' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                ],
            ],
            '~anthropic/claude-haiku-latest' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            '~anthropic/claude-opus-latest' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            '~anthropic/claude-sonnet-latest' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            '~google/gemini-flash-latest' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            '~google/gemini-pro-latest' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_PDF,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::INPUT_MULTIMODAL,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            '~moonshotai/kimi-latest' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            '~openai/gpt-latest' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_PDF,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            '~openai/gpt-mini-latest' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_PDF,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
        ];
        // STATIC LIST END

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
