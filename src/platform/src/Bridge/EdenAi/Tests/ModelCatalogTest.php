<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi\Tests;

use Symfony\AI\Platform\Bridge\EdenAi\DocumentParser;
use Symfony\AI\Platform\Bridge\EdenAi\ImageAnalysis;
use Symfony\AI\Platform\Bridge\EdenAi\ImageGeneration;
use Symfony\AI\Platform\Bridge\EdenAi\ModelCatalog;
use Symfony\AI\Platform\Bridge\EdenAi\Ocr;
use Symfony\AI\Platform\Bridge\EdenAi\SpeechToText;
use Symfony\AI\Platform\Bridge\EdenAi\Tts;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Test\ModelCatalogTestCase;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class ModelCatalogTest extends ModelCatalogTestCase
{
    public static function modelsProvider(): iterable
    {
        yield 'openai/gpt-4o' => [
            'openai/gpt-4o',
            CompletionsModel::class,
            [
                Capability::INPUT_MESSAGES,
                Capability::OUTPUT_TEXT,
                Capability::OUTPUT_STREAMING,
                Capability::TOOL_CALLING,
                Capability::INPUT_IMAGE,
                Capability::INPUT_PDF,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'openai/gpt-4o-mini' => [
            'openai/gpt-4o-mini',
            CompletionsModel::class,
            [
                Capability::INPUT_MESSAGES,
                Capability::OUTPUT_TEXT,
                Capability::OUTPUT_STREAMING,
                Capability::TOOL_CALLING,
                Capability::INPUT_IMAGE,
                Capability::INPUT_PDF,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'openai/gpt-4.1' => [
            'openai/gpt-4.1',
            CompletionsModel::class,
            [
                Capability::INPUT_MESSAGES,
                Capability::OUTPUT_TEXT,
                Capability::OUTPUT_STREAMING,
                Capability::TOOL_CALLING,
                Capability::INPUT_IMAGE,
                Capability::INPUT_PDF,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'openai/gpt-4.1-mini' => [
            'openai/gpt-4.1-mini',
            CompletionsModel::class,
            [
                Capability::INPUT_MESSAGES,
                Capability::OUTPUT_TEXT,
                Capability::OUTPUT_STREAMING,
                Capability::TOOL_CALLING,
                Capability::INPUT_IMAGE,
                Capability::INPUT_PDF,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'anthropic/claude-opus-5' => [
            'anthropic/claude-opus-5',
            CompletionsModel::class,
            [
                Capability::INPUT_MESSAGES,
                Capability::OUTPUT_TEXT,
                Capability::OUTPUT_STREAMING,
                Capability::TOOL_CALLING,
                Capability::INPUT_IMAGE,
                Capability::INPUT_PDF,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'anthropic/claude-sonnet-5' => [
            'anthropic/claude-sonnet-5',
            CompletionsModel::class,
            [
                Capability::INPUT_MESSAGES,
                Capability::OUTPUT_TEXT,
                Capability::OUTPUT_STREAMING,
                Capability::TOOL_CALLING,
                Capability::INPUT_IMAGE,
                Capability::INPUT_PDF,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'anthropic/claude-sonnet-4-5-20250929' => [
            'anthropic/claude-sonnet-4-5-20250929',
            CompletionsModel::class,
            [
                Capability::INPUT_MESSAGES,
                Capability::OUTPUT_TEXT,
                Capability::OUTPUT_STREAMING,
                Capability::TOOL_CALLING,
                Capability::INPUT_IMAGE,
                Capability::INPUT_PDF,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'anthropic/claude-haiku-4-5' => [
            'anthropic/claude-haiku-4-5',
            CompletionsModel::class,
            [
                Capability::INPUT_MESSAGES,
                Capability::OUTPUT_TEXT,
                Capability::OUTPUT_STREAMING,
                Capability::TOOL_CALLING,
                Capability::INPUT_IMAGE,
                Capability::INPUT_PDF,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'google/gemini-2.5-flash' => [
            'google/gemini-2.5-flash',
            CompletionsModel::class,
            [
                Capability::INPUT_MESSAGES,
                Capability::OUTPUT_TEXT,
                Capability::OUTPUT_STREAMING,
                Capability::TOOL_CALLING,
                Capability::INPUT_IMAGE,
                Capability::INPUT_PDF,
                Capability::INPUT_AUDIO,
                Capability::INPUT_VIDEO,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'google/gemini-2.5-pro' => [
            'google/gemini-2.5-pro',
            CompletionsModel::class,
            [
                Capability::INPUT_MESSAGES,
                Capability::OUTPUT_TEXT,
                Capability::OUTPUT_STREAMING,
                Capability::TOOL_CALLING,
                Capability::INPUT_IMAGE,
                Capability::INPUT_PDF,
                Capability::INPUT_AUDIO,
                Capability::INPUT_VIDEO,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'mistral/mistral-large-latest' => [
            'mistral/mistral-large-latest',
            CompletionsModel::class,
            [
                Capability::INPUT_MESSAGES,
                Capability::OUTPUT_TEXT,
                Capability::OUTPUT_STREAMING,
                Capability::TOOL_CALLING,
                Capability::INPUT_IMAGE,
                Capability::INPUT_PDF,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'mistral/mistral-small-latest' => [
            'mistral/mistral-small-latest',
            CompletionsModel::class,
            [
                Capability::INPUT_MESSAGES,
                Capability::OUTPUT_TEXT,
                Capability::OUTPUT_STREAMING,
                Capability::TOOL_CALLING,
                Capability::INPUT_IMAGE,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'cohere/command-a-03-2025' => [
            'cohere/command-a-03-2025',
            CompletionsModel::class,
            [
                Capability::INPUT_MESSAGES,
                Capability::OUTPUT_TEXT,
                Capability::OUTPUT_STREAMING,
                Capability::TOOL_CALLING,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'deepseek/deepseek-chat' => [
            'deepseek/deepseek-chat',
            CompletionsModel::class,
            [
                Capability::INPUT_MESSAGES,
                Capability::OUTPUT_TEXT,
                Capability::OUTPUT_STREAMING,
                Capability::TOOL_CALLING,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'deepseek/deepseek-reasoner' => [
            'deepseek/deepseek-reasoner',
            CompletionsModel::class,
            [
                Capability::INPUT_MESSAGES,
                Capability::OUTPUT_TEXT,
                Capability::OUTPUT_STREAMING,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'openai/text-embedding-3-small' => [
            'openai/text-embedding-3-small',
            EmbeddingsModel::class,
            [
                Capability::INPUT_TEXT,
                Capability::EMBEDDINGS,
            ],
        ];

        yield 'openai/text-embedding-3-large' => [
            'openai/text-embedding-3-large',
            EmbeddingsModel::class,
            [
                Capability::INPUT_TEXT,
                Capability::EMBEDDINGS,
            ],
        ];

        yield 'openai/text-embedding-ada-002' => [
            'openai/text-embedding-ada-002',
            EmbeddingsModel::class,
            [
                Capability::INPUT_TEXT,
                Capability::EMBEDDINGS,
            ],
        ];

        yield 'ocr/ocr/amazon' => [
            'ocr/ocr/amazon',
            Ocr::class,
            [
                Capability::INPUT_PDF,
                Capability::INPUT_IMAGE,
                Capability::OUTPUT_TEXT,
            ],
        ];

        yield 'ocr/ocr/api4ai' => [
            'ocr/ocr/api4ai',
            Ocr::class,
            [
                Capability::INPUT_PDF,
                Capability::INPUT_IMAGE,
                Capability::OUTPUT_TEXT,
            ],
        ];

        yield 'ocr/ocr/google' => [
            'ocr/ocr/google',
            Ocr::class,
            [
                Capability::INPUT_PDF,
                Capability::INPUT_IMAGE,
                Capability::OUTPUT_TEXT,
            ],
        ];

        yield 'ocr/ocr/microsoft' => [
            'ocr/ocr/microsoft',
            Ocr::class,
            [
                Capability::INPUT_PDF,
                Capability::INPUT_IMAGE,
                Capability::OUTPUT_TEXT,
            ],
        ];

        yield 'ocr/ocr/mistral' => [
            'ocr/ocr/mistral',
            Ocr::class,
            [
                Capability::INPUT_PDF,
                Capability::INPUT_IMAGE,
                Capability::OUTPUT_TEXT,
            ],
        ];

        yield 'ocr/ocr/sentisight' => [
            'ocr/ocr/sentisight',
            Ocr::class,
            [
                Capability::INPUT_PDF,
                Capability::INPUT_IMAGE,
                Capability::OUTPUT_TEXT,
            ],
        ];

        yield 'ocr/financial_parser/affinda' => [
            'ocr/financial_parser/affinda',
            DocumentParser::class,
            [
                Capability::INPUT_PDF,
                Capability::INPUT_IMAGE,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'ocr/financial_parser/openai/gpt-4o' => [
            'ocr/financial_parser/openai/gpt-4o',
            DocumentParser::class,
            [
                Capability::INPUT_PDF,
                Capability::INPUT_IMAGE,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'ocr/resume_parser/affinda' => [
            'ocr/resume_parser/affinda',
            DocumentParser::class,
            [
                Capability::INPUT_PDF,
                Capability::INPUT_IMAGE,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'ocr/resume_parser/extracta' => [
            'ocr/resume_parser/extracta',
            DocumentParser::class,
            [
                Capability::INPUT_PDF,
                Capability::INPUT_IMAGE,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'ocr/resume_parser/klippa' => [
            'ocr/resume_parser/klippa',
            DocumentParser::class,
            [
                Capability::INPUT_PDF,
                Capability::INPUT_IMAGE,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'ocr/resume_parser/openai' => [
            'ocr/resume_parser/openai',
            DocumentParser::class,
            [
                Capability::INPUT_PDF,
                Capability::INPUT_IMAGE,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'ocr/resume_parser/openai/gpt-4o' => [
            'ocr/resume_parser/openai/gpt-4o',
            DocumentParser::class,
            [
                Capability::INPUT_PDF,
                Capability::INPUT_IMAGE,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'ocr/resume_parser/senseloaf' => [
            'ocr/resume_parser/senseloaf',
            DocumentParser::class,
            [
                Capability::INPUT_PDF,
                Capability::INPUT_IMAGE,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'ocr/identity_parser/affinda' => [
            'ocr/identity_parser/affinda',
            DocumentParser::class,
            [
                Capability::INPUT_PDF,
                Capability::INPUT_IMAGE,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'ocr/identity_parser/amazon' => [
            'ocr/identity_parser/amazon',
            DocumentParser::class,
            [
                Capability::INPUT_PDF,
                Capability::INPUT_IMAGE,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'ocr/identity_parser/base64' => [
            'ocr/identity_parser/base64',
            DocumentParser::class,
            [
                Capability::INPUT_PDF,
                Capability::INPUT_IMAGE,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'ocr/identity_parser/klippa' => [
            'ocr/identity_parser/klippa',
            DocumentParser::class,
            [
                Capability::INPUT_PDF,
                Capability::INPUT_IMAGE,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'ocr/identity_parser/microsoft' => [
            'ocr/identity_parser/microsoft',
            DocumentParser::class,
            [
                Capability::INPUT_PDF,
                Capability::INPUT_IMAGE,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'ocr/identity_parser/mindee' => [
            'ocr/identity_parser/mindee',
            DocumentParser::class,
            [
                Capability::INPUT_PDF,
                Capability::INPUT_IMAGE,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'ocr/identity_parser/openai' => [
            'ocr/identity_parser/openai',
            DocumentParser::class,
            [
                Capability::INPUT_PDF,
                Capability::INPUT_IMAGE,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'ocr/identity_parser/openai/gpt-4o' => [
            'ocr/identity_parser/openai/gpt-4o',
            DocumentParser::class,
            [
                Capability::INPUT_PDF,
                Capability::INPUT_IMAGE,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'audio/tts/amazon/neural' => [
            'audio/tts/amazon/neural',
            Tts::class,
            [
                Capability::TEXT_TO_SPEECH,
            ],
        ];

        yield 'audio/tts/elevenlabs/eleven_flash_v2' => [
            'audio/tts/elevenlabs/eleven_flash_v2',
            Tts::class,
            [
                Capability::TEXT_TO_SPEECH,
            ],
        ];

        yield 'audio/tts/openai/tts-1' => [
            'audio/tts/openai/tts-1',
            Tts::class,
            [
                Capability::TEXT_TO_SPEECH,
            ],
        ];

        yield 'audio/speech_to_text_async/amazon' => [
            'audio/speech_to_text_async/amazon',
            SpeechToText::class,
            [
                Capability::SPEECH_TO_TEXT,
            ],
        ];

        yield 'audio/speech_to_text_async/deepgram' => [
            'audio/speech_to_text_async/deepgram',
            SpeechToText::class,
            [
                Capability::SPEECH_TO_TEXT,
            ],
        ];

        yield 'audio/speech_to_text_async/google' => [
            'audio/speech_to_text_async/google',
            SpeechToText::class,
            [
                Capability::SPEECH_TO_TEXT,
            ],
        ];

        yield 'audio/speech_to_text_async/openai' => [
            'audio/speech_to_text_async/openai',
            SpeechToText::class,
            [
                Capability::SPEECH_TO_TEXT,
            ],
        ];

        yield 'image/object_detection/amazon' => [
            'image/object_detection/amazon',
            ImageAnalysis::class,
            [
                Capability::INPUT_IMAGE,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'image/object_detection/google' => [
            'image/object_detection/google',
            ImageAnalysis::class,
            [
                Capability::INPUT_IMAGE,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'image/explicit_content/amazon' => [
            'image/explicit_content/amazon',
            ImageAnalysis::class,
            [
                Capability::INPUT_IMAGE,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'image/explicit_content/google' => [
            'image/explicit_content/google',
            ImageAnalysis::class,
            [
                Capability::INPUT_IMAGE,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'image/logo_detection/google' => [
            'image/logo_detection/google',
            ImageAnalysis::class,
            [
                Capability::INPUT_IMAGE,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'image/face_detection/amazon' => [
            'image/face_detection/amazon',
            ImageAnalysis::class,
            [
                Capability::INPUT_IMAGE,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'image/ai_detection/winstonai' => [
            'image/ai_detection/winstonai',
            ImageAnalysis::class,
            [
                Capability::INPUT_IMAGE,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'image/deepfake_detection/sightengine' => [
            'image/deepfake_detection/sightengine',
            ImageAnalysis::class,
            [
                Capability::INPUT_IMAGE,
                Capability::OUTPUT_STRUCTURED,
            ],
        ];

        yield 'image/generation/openai' => [
            'image/generation/openai',
            ImageGeneration::class,
            [
                Capability::TEXT_TO_IMAGE,
            ],
        ];

        yield 'image/generation/openai/gpt-image-1' => [
            'image/generation/openai/gpt-image-1',
            ImageGeneration::class,
            [
                Capability::TEXT_TO_IMAGE,
            ],
        ];

        yield 'image/generation/stabilityai' => [
            'image/generation/stabilityai',
            ImageGeneration::class,
            [
                Capability::TEXT_TO_IMAGE,
            ],
        ];
    }

    public function testAdditionalModelsAreMergedIntoCatalog()
    {
        $catalog = new ModelCatalog([
            'xai/grok-4' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                ],
            ],
        ]);

        $model = $catalog->getModel('xai/grok-4');

        $this->assertInstanceOf(CompletionsModel::class, $model);
        $this->assertSame('xai/grok-4', $model->getName());
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
