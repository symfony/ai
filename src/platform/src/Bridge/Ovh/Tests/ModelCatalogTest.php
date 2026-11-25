<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ovh\Tests;

use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
use Symfony\AI\Platform\Bridge\Ovh\ModelCatalog;
use Symfony\AI\Platform\Bridge\Ovh\SpeechToText;
use Symfony\AI\Platform\Bridge\Ovh\TextToSpeech;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Test\ModelCatalogTestCase;

/**
 * @author Loïc Sapone <loic@sapone.fr>
 */
final class ModelCatalogTest extends ModelCatalogTestCase
{
    public static function modelsProvider(): iterable
    {
        // Completions models
        yield 'Qwen3Guard-Gen-8B' => ['Qwen3Guard-Gen-8B', CompletionsModel::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING]];
        yield 'Qwen3Guard-Gen-0.6B' => ['Qwen3Guard-Gen-0.6B', CompletionsModel::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING]];
        yield 'gpt-oss-20b' => ['gpt-oss-20b', CompletionsModel::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING, Capability::THINKING]];
        yield 'gpt-oss-120b' => ['gpt-oss-120b', CompletionsModel::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING, Capability::THINKING]];
        yield 'gemma-4-26b-a4b' => ['gemma-4-26b-a4b', CompletionsModel::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING, Capability::THINKING]];
        yield 'laguna-xs-2.1' => ['laguna-xs-2.1', CompletionsModel::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING, Capability::THINKING]];
        yield 'nemotron3-nano-30b' => ['nemotron3-nano-30b', CompletionsModel::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING, Capability::THINKING]];
        yield 'nemotron3-omni-30b' => ['nemotron3-omni-30b', CompletionsModel::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING, Capability::THINKING]];
        yield 'DeepSeek-V4.1-Flash-1017' => ['DeepSeek-V4.1-Flash-1017', CompletionsModel::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING, Capability::THINKING]];
        yield 'Qwen3.5-397B-A17B' => ['Qwen3.5-397B-A17B', CompletionsModel::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING, Capability::THINKING]];
        yield 'Qwen3.5-9B' => ['Qwen3.5-9B', CompletionsModel::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING, Capability::THINKING]];
        yield 'Qwen3.6-27B' => ['Qwen3.6-27B', CompletionsModel::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING, Capability::THINKING]];
        yield 'Qwen3.8-27B' => ['Qwen3.8-27B', CompletionsModel::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING, Capability::THINKING]];
        yield 'qwen3.6-35b-a3b' => ['qwen3.6-35b-a3b', CompletionsModel::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING, Capability::THINKING]];
        yield 'qwen3-vl-30b-a3b' => ['qwen3-vl-30b-a3b', CompletionsModel::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'Mistral-Small-3.2-24B-Instruct-2506' => ['Mistral-Small-3.2-24B-Instruct-2506', CompletionsModel::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'Qwen2.5-VL-72B-Instruct' => ['Qwen2.5-VL-72B-Instruct', CompletionsModel::class, [Capability::INPUT_MESSAGES, Capability::INPUT_IMAGE, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED]];
        yield 'Meta-Llama-3_3-70B-Instruct' => ['Meta-Llama-3_3-70B-Instruct', CompletionsModel::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING]];
        yield 'Olala-v1.0-mini-7A1B' => ['Olala-v1.0-mini-7A1B', CompletionsModel::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING, Capability::THINKING]];
        yield 'Olala-v1.0-summarise' => ['Olala-v1.0-summarise', CompletionsModel::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING, Capability::OUTPUT_STRUCTURED, Capability::TOOL_CALLING, Capability::THINKING]];
        yield 'Olala-v1.0-translate' => ['Olala-v1.0-translate', CompletionsModel::class, [Capability::INPUT_MESSAGES, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING]];

        // Embeddings models
        yield 'bge-multilingual-gemma2' => ['bge-multilingual-gemma2', EmbeddingsModel::class, [Capability::INPUT_TEXT, Capability::EMBEDDINGS]];
        yield 'bge-m3' => ['bge-m3', EmbeddingsModel::class, [Capability::INPUT_TEXT, Capability::EMBEDDINGS]];
        yield 'Qwen3-Embedding-8B' => ['Qwen3-Embedding-8B', EmbeddingsModel::class, [Capability::INPUT_TEXT, Capability::EMBEDDINGS]];

        // Speech-to-text models
        yield 'whisper-large-v3' => ['whisper-large-v3', SpeechToText::class, [Capability::INPUT_AUDIO, Capability::OUTPUT_TEXT, Capability::SPEECH_TO_TEXT]];
        yield 'whisper-large-v3-turbo' => ['whisper-large-v3-turbo', SpeechToText::class, [Capability::INPUT_AUDIO, Capability::OUTPUT_TEXT, Capability::SPEECH_TO_TEXT]];

        // Text-to-speech models
        yield 'nvr-tts-de-de' => ['nvr-tts-de-de', TextToSpeech::class, [Capability::INPUT_TEXT, Capability::OUTPUT_AUDIO, Capability::TEXT_TO_SPEECH]];
        yield 'nvr-tts-en-us' => ['nvr-tts-en-us', TextToSpeech::class, [Capability::INPUT_TEXT, Capability::OUTPUT_AUDIO, Capability::TEXT_TO_SPEECH]];
        yield 'nvr-tts-es-es' => ['nvr-tts-es-es', TextToSpeech::class, [Capability::INPUT_TEXT, Capability::OUTPUT_AUDIO, Capability::TEXT_TO_SPEECH]];
        yield 'nvr-tts-it-it' => ['nvr-tts-it-it', TextToSpeech::class, [Capability::INPUT_TEXT, Capability::OUTPUT_AUDIO, Capability::TEXT_TO_SPEECH]];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
