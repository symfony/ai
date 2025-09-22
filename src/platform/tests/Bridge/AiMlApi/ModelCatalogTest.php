<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\AiMlApi;

use Symfony\AI\Platform\Bridge\AiMlApi\Completions;
use Symfony\AI\Platform\Bridge\AiMlApi\Embeddings;
use Symfony\AI\Platform\Bridge\AiMlApi\ModelCatalog;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Tests\ModelCatalogTestCase;

final class ModelCatalogTest extends ModelCatalogTestCase
{
    public static function modelsProvider(): iterable
    {
        // Completion models (GPT variants)
        yield 'gpt-3.5-turbo' => ['gpt-3.5-turbo', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'gpt-3.5-turbo-0125' => ['gpt-3.5-turbo-0125', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'gpt-3.5-turbo-1106' => ['gpt-3.5-turbo-1106', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'gpt-4o' => ['gpt-4o', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE, Capability::OUTPUT_STRUCTURED]];
        yield 'gpt-4o-2024-08-06' => ['gpt-4o-2024-08-06', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE, Capability::OUTPUT_STRUCTURED]];
        yield 'gpt-4o-2024-05-13' => ['gpt-4o-2024-05-13', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE, Capability::OUTPUT_STRUCTURED]];
        yield 'gpt-4o-mini' => ['gpt-4o-mini', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE, Capability::OUTPUT_STRUCTURED]];
        yield 'gpt-4o-mini-2024-07-18' => ['gpt-4o-mini-2024-07-18', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE, Capability::OUTPUT_STRUCTURED]];
        yield 'gpt-4-turbo' => ['gpt-4-turbo', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE]];
        yield 'gpt-4' => ['gpt-4', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'gpt-4-turbo-2024-04-09' => ['gpt-4-turbo-2024-04-09', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'gpt-4-0125-preview' => ['gpt-4-0125-preview', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'gpt-4-1106-preview' => ['gpt-4-1106-preview', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'chatgpt-4o-latest' => ['chatgpt-4o-latest', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE, Capability::OUTPUT_STRUCTURED]];
        yield 'gpt-4o-audio-preview' => ['gpt-4o-audio-preview', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE, Capability::OUTPUT_STRUCTURED]];
        yield 'gpt-4o-mini-audio-preview' => ['gpt-4o-mini-audio-preview', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE, Capability::OUTPUT_STRUCTURED]];
        yield 'gpt-4o-search-preview' => ['gpt-4o-search-preview', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE, Capability::OUTPUT_STRUCTURED]];
        yield 'gpt-4o-mini-search-preview' => ['gpt-4o-mini-search-preview', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE, Capability::OUTPUT_STRUCTURED]];
        yield 'o1-mini' => ['o1-mini', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::INPUT_IMAGE]];
        yield 'o1-mini-2024-09-12' => ['o1-mini-2024-09-12', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::INPUT_IMAGE]];
        yield 'o1' => ['o1', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::INPUT_IMAGE]];
        yield 'o3-mini' => ['o3-mini', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::INPUT_IMAGE, Capability::OUTPUT_STRUCTURED]];

        // OpenAI future models
        yield 'openai/o3-2025-04-16' => ['openai/o3-2025-04-16', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::INPUT_IMAGE]];
        yield 'openai/o3-pro' => ['openai/o3-pro', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::INPUT_IMAGE]];
        yield 'openai/gpt-4.1-2025-04-14' => ['openai/gpt-4.1-2025-04-14', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'openai/gpt-4.1-mini-2025-04-14' => ['openai/gpt-4.1-mini-2025-04-14', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'openai/gpt-4.1-nano-2025-04-14' => ['openai/gpt-4.1-nano-2025-04-14', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'openai/o4-mini-2025-04-16' => ['openai/o4-mini-2025-04-16', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::INPUT_IMAGE]];
        yield 'openai/gpt-oss-20b' => ['openai/gpt-oss-20b', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'openai/gpt-oss-120b' => ['openai/gpt-oss-120b', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'openai/gpt-5-2025-08-07' => ['openai/gpt-5-2025-08-07', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'openai/gpt-5-mini-2025-08-07' => ['openai/gpt-5-mini-2025-08-07', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'openai/gpt-5-nano-2025-08-07' => ['openai/gpt-5-nano-2025-08-07', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'openai/gpt-5-chat-latest' => ['openai/gpt-5-chat-latest', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];

        // DeepSeek models
        yield 'deepseek-chat' => ['deepseek-chat', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'deepseek/deepseek-chat' => ['deepseek/deepseek-chat', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'deepseek/deepseek-chat-v3-0324' => ['deepseek/deepseek-chat-v3-0324', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'deepseek/deepseek-r1' => ['deepseek/deepseek-r1', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]];
        yield 'deepseek-reasoner' => ['deepseek-reasoner', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]];
        yield 'deepseek/deepseek-prover-v2' => ['deepseek/deepseek-prover-v2', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]];
        yield 'deepseek/deepseek-chat-v3.1' => ['deepseek/deepseek-chat-v3.1', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'deepseek/deepseek-reasoner-v3.1' => ['deepseek/deepseek-reasoner-v3.1', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]];

        // Qwen models
        yield 'Qwen/Qwen2-72B-Instruct' => ['Qwen/Qwen2-72B-Instruct', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'qwen-max' => ['qwen-max', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'qwen-plus' => ['qwen-plus', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'qwen-turbo' => ['qwen-turbo', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'qwen-max-2025-01-25' => ['qwen-max-2025-01-25', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'Qwen/Qwen2.5-72B-Instruct-Turbo' => ['Qwen/Qwen2.5-72B-Instruct-Turbo', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'Qwen/QwQ-32B' => ['Qwen/QwQ-32B', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'Qwen/Qwen3-235B-A22B-fp8-tput' => ['Qwen/Qwen3-235B-A22B-fp8-tput', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'alibaba/qwen3-32b' => ['alibaba/qwen3-32b', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'alibaba/qwen3-coder-480b-a35b-instruct' => ['alibaba/qwen3-coder-480b-a35b-instruct', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'alibaba/qwen3-235b-a22b-thinking-2507' => ['alibaba/qwen3-235b-a22b-thinking-2507', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT]];
        yield 'Qwen/Qwen2.5-7B-Instruct-Turbo' => ['Qwen/Qwen2.5-7B-Instruct-Turbo', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'Qwen/Qwen2.5-Coder-32B-Instruct' => ['Qwen/Qwen2.5-Coder-32B-Instruct', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];

        // Mistral models
        yield 'mistralai/Mixtral-8x7B-Instruct-v0.1' => ['mistralai/Mixtral-8x7B-Instruct-v0.1', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'mistralai/Mistral-7B-Instruct-v0.2' => ['mistralai/Mistral-7B-Instruct-v0.2', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'mistralai/Mistral-7B-Instruct-v0.1' => ['mistralai/Mistral-7B-Instruct-v0.1', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'mistralai/Mistral-7B-Instruct-v0.3' => ['mistralai/Mistral-7B-Instruct-v0.3', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'mistralai/mistral-tiny' => ['mistralai/mistral-tiny', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'mistralai/mistral-nemo' => ['mistralai/mistral-nemo', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'mistralai/codestral-2501' => ['mistralai/codestral-2501', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];

        // Meta Llama models
        yield 'meta-llama/Llama-3.3-70B-Instruct-Turbo' => ['meta-llama/Llama-3.3-70B-Instruct-Turbo', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'meta-llama/Llama-3.2-3B-Instruct-Turbo' => ['meta-llama/Llama-3.2-3B-Instruct-Turbo', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'meta-llama/Meta-Llama-3-8B-Instruct-Lite' => ['meta-llama/Meta-Llama-3-8B-Instruct-Lite', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'meta-llama/Llama-3-70b-chat-hf' => ['meta-llama/Llama-3-70b-chat-hf', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'meta-llama/Meta-Llama-3.1-405B-Instruct-Turbo' => ['meta-llama/Meta-Llama-3.1-405B-Instruct-Turbo', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'meta-llama/Meta-Llama-3.1-8B-Instruct-Turbo' => ['meta-llama/Meta-Llama-3.1-8B-Instruct-Turbo', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'meta-llama/Meta-Llama-3.1-70B-Instruct-Turbo' => ['meta-llama/Meta-Llama-3.1-70B-Instruct-Turbo', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'meta-llama/llama-4-scout' => ['meta-llama/llama-4-scout', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'meta-llama/llama-4-maverick' => ['meta-llama/llama-4-maverick', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];

        // Claude models
        yield 'claude-3-opus-20240229' => ['claude-3-opus-20240229', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE]];
        yield 'claude-3-haiku-20240307' => ['claude-3-haiku-20240307', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE]];
        yield 'claude-3-5-sonnet-20240620' => ['claude-3-5-sonnet-20240620', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE]];
        yield 'claude-3-5-sonnet-20241022' => ['claude-3-5-sonnet-20241022', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE]];
        yield 'claude-3-5-haiku-20241022' => ['claude-3-5-haiku-20241022', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE]];
        yield 'claude-3-7-sonnet-20250219' => ['claude-3-7-sonnet-20250219', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE]];
        yield 'anthropic/claude-opus-4' => ['anthropic/claude-opus-4', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE]];
        yield 'anthropic/claude-sonnet-4' => ['anthropic/claude-sonnet-4', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE]];
        yield 'anthropic/claude-opus-4.1' => ['anthropic/claude-opus-4.1', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE]];
        yield 'claude-opus-4-1' => ['claude-opus-4-1', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE]];
        yield 'claude-opus-4-1-20250805' => ['claude-opus-4-1-20250805', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE]];

        // Gemini models
        yield 'gemini-2.0-flash-exp' => ['gemini-2.0-flash-exp', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE]];
        yield 'gemini-2.0-flash' => ['gemini-2.0-flash', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE]];
        yield 'google/gemini-2.5-flash-lite-preview' => ['google/gemini-2.5-flash-lite-preview', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE]];
        yield 'google/gemini-2.5-flash' => ['google/gemini-2.5-flash', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE]];
        yield 'google/gemini-2.5-pro' => ['google/gemini-2.5-pro', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING, Capability::INPUT_IMAGE]];
        yield 'google/gemma-2-27b-it' => ['google/gemma-2-27b-it', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'google/gemma-3-4b-it' => ['google/gemma-3-4b-it', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'google/gemma-3-12b-it' => ['google/gemma-3-12b-it', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'google/gemma-3-27b-it' => ['google/gemma-3-27b-it', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'google/gemma-3n-e4b-it' => ['google/gemma-3n-e4b-it', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];

        // X.AI models
        yield 'x-ai/grok-3-beta' => ['x-ai/grok-3-beta', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'x-ai/grok-3-mini-beta' => ['x-ai/grok-3-mini-beta', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'x-ai/grok-4-07-09' => ['x-ai/grok-4-07-09', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];

        // Other models
        yield 'anthracite-org/magnum-v4-72b' => ['anthracite-org/magnum-v4-72b', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'nvidia/llama-3.1-nemotron-70b-instruct' => ['nvidia/llama-3.1-nemotron-70b-instruct', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'cohere/command-r-plus' => ['cohere/command-r-plus', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'cohere/command-a' => ['cohere/command-a', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'MiniMax-Text-01' => ['MiniMax-Text-01', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'minimax/m1' => ['minimax/m1', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'moonshot/kimi-k2-preview' => ['moonshot/kimi-k2-preview', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'perplexity/sonar' => ['perplexity/sonar', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'perplexity/sonar-pro' => ['perplexity/sonar-pro', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'zhipu/glm-4.5-air' => ['zhipu/glm-4.5-air', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];
        yield 'zhipu/glm-4.5' => ['zhipu/glm-4.5', Completions::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::TOOL_CALLING]];

        // Embedding models
        yield 'text-embedding-3-small' => ['text-embedding-3-small', Embeddings::class, [Capability::INPUT_MULTIPLE]];
        yield 'text-embedding-3-large' => ['text-embedding-3-large', Embeddings::class, [Capability::INPUT_MULTIPLE]];
        yield 'text-embedding-ada-002' => ['text-embedding-ada-002', Embeddings::class, [Capability::INPUT_MULTIPLE]];
        yield 'togethercomputer/m2-bert-80M-32k-retrieval' => ['togethercomputer/m2-bert-80M-32k-retrieval', Embeddings::class, [Capability::INPUT_MULTIPLE]];
        yield 'BAAI/bge-base-en-v1.5' => ['BAAI/bge-base-en-v1.5', Embeddings::class, [Capability::INPUT_MULTIPLE]];
        yield 'BAAI/bge-large-en-v1.' => ['BAAI/bge-large-en-v1.', Embeddings::class, [Capability::INPUT_MULTIPLE]];
        yield 'voyage-large-2-instruct' => ['voyage-large-2-instruct', Embeddings::class, [Capability::INPUT_MULTIPLE]];
        yield 'voyage-finance-2' => ['voyage-finance-2', Embeddings::class, [Capability::INPUT_MULTIPLE]];
        yield 'voyage-multilingual-2' => ['voyage-multilingual-2', Embeddings::class, [Capability::INPUT_MULTIPLE]];
        yield 'voyage-law-2' => ['voyage-law-2', Embeddings::class, [Capability::INPUT_MULTIPLE]];
        yield 'voyage-code-2' => ['voyage-code-2', Embeddings::class, [Capability::INPUT_MULTIPLE]];
        yield 'voyage-large-2' => ['voyage-large-2', Embeddings::class, [Capability::INPUT_MULTIPLE]];
        yield 'voyage-2' => ['voyage-2', Embeddings::class, [Capability::INPUT_MULTIPLE]];
        yield 'textembedding-gecko@003' => ['textembedding-gecko@003', Embeddings::class, [Capability::INPUT_MULTIPLE]];
        yield 'textembedding-gecko-multilingual@001' => ['textembedding-gecko-multilingual@001', Embeddings::class, [Capability::INPUT_MULTIPLE]];
        yield 'text-multilingual-embedding-002' => ['text-multilingual-embedding-002', Embeddings::class, [Capability::INPUT_MULTIPLE]];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
