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

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;

/**
 * @author Tim Lochmüller <tim@fruit-lab.de
 */
class Completions extends Model
{
    public const GPT_3_5_TURBO = 'gpt-3.5-turbo';
    public const GPT_3_5_TURBO_0125 = 'gpt-3.5-turbo-0125';
    public const GPT_3_5_TURBO_1106 = 'gpt-3.5-turbo-1106';
    public const GPT_4O = 'gpt-4o';
    public const GPT_4O_2024_08_06 = 'gpt-4o-2024-08-06';
    public const GPT_4O_2024_05_13 = 'gpt-4o-2024-05-13';
    public const GPT_4O_MINI = 'gpt-4o-mini';
    public const GPT_4O_MINI_2024_07_18 = 'gpt-4o-mini-2024-07-18';
    public const CHATGPT_4O_LATEST = 'chatgpt-4o-latest';
    public const GPT_4O_AUDIO_PREVIEW = 'gpt-4o-audio-preview';
    public const GPT_4O_MINI_AUDIO_PREVIEW = 'gpt-4o-mini-audio-preview';
    public const GPT_4O_SEARCH_PREVIEW = 'gpt-4o-search-preview';
    public const GPT_4O_MINI_SEARCH_PREVIEW = 'gpt-4o-mini-search-preview';
    public const GPT_4_TURBO = 'gpt-4-turbo';
    public const GPT_4_TURBO_2024_04_09 = 'gpt-4-turbo-2024-04-09';
    public const GPT_4 = 'gpt-4';
    public const GPT_4_0125_PREVIEW = 'gpt-4-0125-preview';
    public const GPT_4_1106_PREVIEW = 'gpt-4-1106-preview';
    public const O1_MINI = 'o1-mini';
    public const O1_MINI_2024_09_12 = 'o1-mini-2024-09-12';
    public const O1 = 'o1';
    public const OPENAI_O3_2025_04_16 = 'openai/o3-2025-04-16';
    public const O3_MINI = 'o3-mini';
    public const OPENAI_O3_PRO = 'openai/o3-pro';
    public const OPENAI_GPT_4_1_2025_04_14 = 'openai/gpt-4.1-2025-04-14';
    public const OPENAI_GPT_4_1_MINI_2025_04_14 = 'openai/gpt-4.1-mini-2025-04-14';
    public const OPENAI_GPT_4_1_NANO_2025_04_14 = 'openai/gpt-4.1-nano-2025-04-14';
    public const OPENAI_O4_MINI_2025_04_16 = 'openai/o4-mini-2025-04-16';
    public const OPENAI_GPT_OSS_20B = 'openai/gpt-oss-20b';
    public const OPENAI_GPT_OSS_120B = 'openai/gpt-oss-120b';
    public const OPENAI_GPT_5_2025_08_07 = 'openai/gpt-5-2025-08-07';
    public const OPENAI_GPT_5_MINI_2025_08_07 = 'openai/gpt-5-mini-2025-08-07';
    public const OPENAI_GPT_5_NANO_2025_08_07 = 'openai/gpt-5-nano-2025-08-07';
    public const OPENAI_GPT_5_CHAT_LATEST = 'openai/gpt-5-chat-latest';
    public const DEEPSEEK_CHAT = 'deepseek-chat';
    public const DEEPSEEK_DEEPSEEK_CHAT = 'deepseek/deepseek-chat';
    public const DEEPSEEK_DEEPSEEK_CHAT_V3_0324 = 'deepseek/deepseek-chat-v3-0324';
    public const DEEPSEEK_DEEPSEEK_R1 = 'deepseek/deepseek-r1';
    public const DEEPSEEK_REASONER = 'deepseek-reasoner';
    public const DEEPSEEK_DEEPSEEK_PROVER_V2 = 'deepseek/deepseek-prover-v2';
    public const DEEPSEEK_DEEPSEEK_CHAT_V3_1 = 'deepseek/deepseek-chat-v3.1';
    public const DEEPSEEK_DEEPSEEK_REASONER_V3_1 = 'deepseek/deepseek-reasoner-v3.1';
    public const QWEN_QWEN2_72B_INSTRUCT = 'Qwen/Qwen2-72B-Instruct';
    public const MISTRALAI_MIXTRAL_8X7B_INSTRUCT_V0_1 = 'mistralai/Mixtral-8x7B-Instruct-v0.1';
    public const META_LLAMA_LLAMA_3_3_70B_INSTRUCT_TURBO = 'meta-llama/Llama-3.3-70B-Instruct-Turbo';
    public const META_LLAMA_LLAMA_3_2_3B_INSTRUCT_TURBO = 'meta-llama/Llama-3.2-3B-Instruct-Turbo';
    public const QWEN_QWEN2_5_7B_INSTRUCT_TURBO = 'Qwen/Qwen2.5-7B-Instruct-Turbo';
    public const QWEN_QWEN2_5_CODER_32B_INSTRUCT = 'Qwen/Qwen2.5-Coder-32B-Instruct';
    public const META_LLAMA_META_LLAMA_3_8B_INSTRUCT_LITE = 'meta-llama/Meta-Llama-3-8B-Instruct-Lite';
    public const META_LLAMA_LLAMA_3_70B_CHAT_HF = 'meta-llama/Llama-3-70b-chat-hf';
    public const META_LLAMA_META_LLAMA_3_1_405B_INSTRUCT_TURBO = 'meta-llama/Meta-Llama-3.1-405B-Instruct-Turbo';
    public const META_LLAMA_META_LLAMA_3_1_8B_INSTRUCT_TURBO = 'meta-llama/Meta-Llama-3.1-8B-Instruct-Turbo';
    public const META_LLAMA_META_LLAMA_3_1_70B_INSTRUCT_TURBO = 'meta-llama/Meta-Llama-3.1-70B-Instruct-Turbo';
    public const META_LLAMA_LLAMA_4_SCOUT = 'meta-llama/llama-4-scout';
    public const META_LLAMA_LLAMA_4_MAVERICK = 'meta-llama/llama-4-maverick';
    public const MISTRALAI_MISTRAL_7B_INSTRUCT_V0_2 = 'mistralai/Mistral-7B-Instruct-v0.2';
    public const MISTRALAI_MISTRAL_7B_INSTRUCT_V0_1 = 'mistralai/Mistral-7B-Instruct-v0.1';
    public const MISTRALAI_MISTRAL_7B_INSTRUCT_V0_3 = 'mistralai/Mistral-7B-Instruct-v0.3';
    public const CLAUDE_3_OPUS_20240229 = 'claude-3-opus-20240229';
    public const CLAUDE_3_HAIKU_20240307 = 'claude-3-haiku-20240307';
    public const CLAUDE_3_5_SONNET_20240620 = 'claude-3-5-sonnet-20240620';
    public const CLAUDE_3_5_SONNET_20241022 = 'claude-3-5-sonnet-20241022';
    public const CLAUDE_3_5_HAIKU_20241022 = 'claude-3-5-haiku-20241022';
    public const CLAUDE_3_7_SONNET_20250219 = 'claude-3-7-sonnet-20250219';
    public const ANTHROPIC_CLAUDE_OPUS_4 = 'anthropic/claude-opus-4';
    public const ANTHROPIC_CLAUDE_SONNET_4 = 'anthropic/claude-sonnet-4';
    public const ANTHROPIC_CLAUDE_OPUS_4_1 = 'anthropic/claude-opus-4.1';
    public const CLAUDE_OPUS_4_1 = 'claude-opus-4-1';
    public const CLAUDE_OPUS_4_1_20250805 = 'claude-opus-4-1-20250805';
    public const GEMINI_2_0_FLASH_EXP = 'gemini-2.0-flash-exp';
    public const GEMINI_2_0_FLASH = 'gemini-2.0-flash';
    public const GOOGLE_GEMINI_2_5_FLASH_LITE_PREVIEW = 'google/gemini-2.5-flash-lite-preview';
    public const GOOGLE_GEMINI_2_5_FLASH = 'google/gemini-2.5-flash';
    public const GOOGLE_GEMINI_2_5_PRO = 'google/gemini-2.5-pro';
    public const GOOGLE_GEMMA_2_27B_IT = 'google/gemma-2-27b-it';
    public const GOOGLE_GEMMA_3_4B_IT = 'google/gemma-3-4b-it';
    public const GOOGLE_GEMMA_3_12B_IT = 'google/gemma-3-12b-it';
    public const GOOGLE_GEMMA_3_27B_IT = 'google/gemma-3-27b-it';
    public const GOOGLE_GEMMA_3N_E4B_IT = 'google/gemma-3n-e4b-it';
    public const QWEN_MAX = 'qwen-max';
    public const QWEN_PLUS = 'qwen-plus';
    public const QWEN_TURBO = 'qwen-turbo';
    public const QWEN_MAX_2025_01_25 = 'qwen-max-2025-01-25';
    public const QWEN_QWEN2_5_72B_INSTRUCT_TURBO = 'Qwen/Qwen2.5-72B-Instruct-Turbo';
    public const QWEN_QWQ_32B = 'Qwen/QwQ-32B';
    public const QWEN_QWEN3_235B_A22B_FP8_TPUT = 'Qwen/Qwen3-235B-A22B-fp8-tput';
    public const ALIBABA_QWEN3_32B = 'alibaba/qwen3-32b';
    public const ALIBABA_QWEN3_CODER_480B_A35B_INSTRUCT = 'alibaba/qwen3-coder-480b-a35b-instruct';
    public const ALIBABA_QWEN3_235B_A22B_THINKING_2507 = 'alibaba/qwen3-235b-a22b-thinking-2507';
    public const MISTRALAI_MISTRAL_TINY = 'mistralai/mistral-tiny';
    public const X_AI_GROK_3_BETA = 'x-ai/grok-3-beta';
    public const X_AI_GROK_3_MINI_BETA = 'x-ai/grok-3-mini-beta';
    public const X_AI_GROK_4_07_09 = 'x-ai/grok-4-07-09';
    public const MISTRALAI_MISTRAL_NEMO = 'mistralai/mistral-nemo';
    public const ANTHRACITE_ORG_MAGNUM_V4_72B = 'anthracite-org/magnum-v4-72b';
    public const NVIDIA_LLAMA_3_1_NEMOTRON_70B_INSTRUCT = 'nvidia/llama-3.1-nemotron-70b-instruct';
    public const COHERE_COMMAND_R_PLUS = 'cohere/command-r-plus';
    public const COHERE_COMMAND_A = 'cohere/command-a';
    public const MISTRALAI_CODESTRAL_2501 = 'mistralai/codestral-2501';
    public const MINIMAX_TEXT_01 = 'MiniMax-Text-01';
    public const MINIMAX_M1 = 'minimax/m1';
    public const MOONSHOT_KIMI_K2_PREVIEW = 'moonshot/kimi-k2-preview';
    public const PERPLEXITY_SONAR = 'perplexity/sonar';
    public const PERPLEXITY_SONAR_PRO = 'perplexity/sonar-pro';
    public const ZHIPU_GLM_4_5_AIR = 'zhipu/glm-4.5-air';
    public const ZHIPU_GLM_4_5 = 'zhipu/glm-4.5';

    public const DEFAULT_CAPABILITIES = [
        Capability::INPUT_MESSAGES,
        Capability::OUTPUT_TEXT,
        Capability::OUTPUT_STREAMING,
    ];

    public function __construct(
        string $name,
        array $options = [],
        array $capabilities = self::DEFAULT_CAPABILITIES,
    ) {
        parent::__construct($name, $capabilities, $options);
    }
}
