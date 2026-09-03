<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi;

use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * Eden AI is an AI gateway giving access to hundreds of models from various providers
 * through a single OpenAI-compatible API, using "provider/model" identifiers.
 *
 * This catalog only contains a subset of popular models as defaults. Any other model
 * available on Eden AI can be registered through the $additionalModels constructor
 * argument.
 *
 * @see https://www.edenai.co/docs
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    private const OCR_CAPABILITIES = [
        Capability::INPUT_PDF,
        Capability::INPUT_IMAGE,
        Capability::OUTPUT_TEXT,
    ];

    private const DOCUMENT_PARSER_CAPABILITIES = [
        Capability::INPUT_PDF,
        Capability::INPUT_IMAGE,
        Capability::OUTPUT_STRUCTURED,
    ];

    private const TTS_CAPABILITIES = [
        Capability::TEXT_TO_SPEECH,
    ];

    private const SPEECH_TO_TEXT_CAPABILITIES = [
        Capability::SPEECH_TO_TEXT,
    ];

    private const IMAGE_ANALYSIS_CAPABILITIES = [
        Capability::INPUT_IMAGE,
        Capability::OUTPUT_STRUCTURED,
    ];

    private const IMAGE_GENERATION_CAPABILITIES = [
        Capability::TEXT_TO_IMAGE,
    ];

    /**
     * @param array<string, array{class: class-string, capabilities: list<Capability>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        $defaultModels = [
            // OpenAI models
            'openai/gpt-4o' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-4o-mini' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-4.1' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'openai/gpt-4.1-mini' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            // Anthropic models
            'anthropic/claude-opus-5' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'anthropic/claude-sonnet-5' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'anthropic/claude-sonnet-4-5-20250929' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'anthropic/claude-haiku-4-5' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            // Google models
            'google/gemini-2.5-flash' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
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
            ],
            'google/gemini-2.5-pro' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
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
            ],
            // Mistral models
            'mistral/mistral-large-latest' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'mistral/mistral-small-latest' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            // Cohere models
            'cohere/command-a-03-2025' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            // DeepSeek models
            'deepseek/deepseek-chat' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'deepseek/deepseek-reasoner' => [
                'class' => CompletionsModel::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            // Embedding models
            'openai/text-embedding-3-small' => [
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
            'openai/text-embedding-ada-002' => [
                'class' => EmbeddingsModel::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            // OCR expert models
            'ocr/ocr/amazon' => [
                'class' => Ocr::class,
                'capabilities' => self::OCR_CAPABILITIES,
            ],
            'ocr/ocr/api4ai' => [
                'class' => Ocr::class,
                'capabilities' => self::OCR_CAPABILITIES,
            ],
            'ocr/ocr/google' => [
                'class' => Ocr::class,
                'capabilities' => self::OCR_CAPABILITIES,
            ],
            'ocr/ocr/microsoft' => [
                'class' => Ocr::class,
                'capabilities' => self::OCR_CAPABILITIES,
            ],
            'ocr/ocr/mistral' => [
                'class' => Ocr::class,
                'capabilities' => self::OCR_CAPABILITIES,
            ],
            'ocr/ocr/sentisight' => [
                'class' => Ocr::class,
                'capabilities' => self::OCR_CAPABILITIES,
            ],
            // Document parsing expert models
            'ocr/financial_parser/affinda' => [
                'class' => DocumentParser::class,
                'capabilities' => self::DOCUMENT_PARSER_CAPABILITIES,
            ],
            'ocr/financial_parser/openai/gpt-4o' => [
                'class' => DocumentParser::class,
                'capabilities' => self::DOCUMENT_PARSER_CAPABILITIES,
            ],
            'ocr/resume_parser/affinda' => [
                'class' => DocumentParser::class,
                'capabilities' => self::DOCUMENT_PARSER_CAPABILITIES,
            ],
            'ocr/resume_parser/extracta' => [
                'class' => DocumentParser::class,
                'capabilities' => self::DOCUMENT_PARSER_CAPABILITIES,
            ],
            'ocr/resume_parser/klippa' => [
                'class' => DocumentParser::class,
                'capabilities' => self::DOCUMENT_PARSER_CAPABILITIES,
            ],
            'ocr/resume_parser/openai' => [
                'class' => DocumentParser::class,
                'capabilities' => self::DOCUMENT_PARSER_CAPABILITIES,
            ],
            'ocr/resume_parser/openai/gpt-4o' => [
                'class' => DocumentParser::class,
                'capabilities' => self::DOCUMENT_PARSER_CAPABILITIES,
            ],
            'ocr/resume_parser/senseloaf' => [
                'class' => DocumentParser::class,
                'capabilities' => self::DOCUMENT_PARSER_CAPABILITIES,
            ],
            'ocr/identity_parser/affinda' => [
                'class' => DocumentParser::class,
                'capabilities' => self::DOCUMENT_PARSER_CAPABILITIES,
            ],
            'ocr/identity_parser/amazon' => [
                'class' => DocumentParser::class,
                'capabilities' => self::DOCUMENT_PARSER_CAPABILITIES,
            ],
            'ocr/identity_parser/base64' => [
                'class' => DocumentParser::class,
                'capabilities' => self::DOCUMENT_PARSER_CAPABILITIES,
            ],
            'ocr/identity_parser/klippa' => [
                'class' => DocumentParser::class,
                'capabilities' => self::DOCUMENT_PARSER_CAPABILITIES,
            ],
            'ocr/identity_parser/microsoft' => [
                'class' => DocumentParser::class,
                'capabilities' => self::DOCUMENT_PARSER_CAPABILITIES,
            ],
            'ocr/identity_parser/mindee' => [
                'class' => DocumentParser::class,
                'capabilities' => self::DOCUMENT_PARSER_CAPABILITIES,
            ],
            'ocr/identity_parser/openai' => [
                'class' => DocumentParser::class,
                'capabilities' => self::DOCUMENT_PARSER_CAPABILITIES,
            ],
            'ocr/identity_parser/openai/gpt-4o' => [
                'class' => DocumentParser::class,
                'capabilities' => self::DOCUMENT_PARSER_CAPABILITIES,
            ],
            // Text-to-speech expert models
            'audio/tts/amazon/neural' => [
                'class' => Tts::class,
                'capabilities' => self::TTS_CAPABILITIES,
            ],
            'audio/tts/elevenlabs/eleven_flash_v2' => [
                'class' => Tts::class,
                'capabilities' => self::TTS_CAPABILITIES,
            ],
            'audio/tts/openai/tts-1' => [
                'class' => Tts::class,
                'capabilities' => self::TTS_CAPABILITIES,
            ],
            // Speech-to-text expert models
            'audio/speech_to_text_async/amazon' => [
                'class' => SpeechToText::class,
                'capabilities' => self::SPEECH_TO_TEXT_CAPABILITIES,
            ],
            'audio/speech_to_text_async/deepgram' => [
                'class' => SpeechToText::class,
                'capabilities' => self::SPEECH_TO_TEXT_CAPABILITIES,
            ],
            'audio/speech_to_text_async/google' => [
                'class' => SpeechToText::class,
                'capabilities' => self::SPEECH_TO_TEXT_CAPABILITIES,
            ],
            'audio/speech_to_text_async/openai' => [
                'class' => SpeechToText::class,
                'capabilities' => self::SPEECH_TO_TEXT_CAPABILITIES,
            ],
            // Image analysis expert models
            'image/object_detection/amazon' => [
                'class' => ImageAnalysis::class,
                'capabilities' => self::IMAGE_ANALYSIS_CAPABILITIES,
            ],
            'image/object_detection/google' => [
                'class' => ImageAnalysis::class,
                'capabilities' => self::IMAGE_ANALYSIS_CAPABILITIES,
            ],
            'image/explicit_content/amazon' => [
                'class' => ImageAnalysis::class,
                'capabilities' => self::IMAGE_ANALYSIS_CAPABILITIES,
            ],
            'image/explicit_content/google' => [
                'class' => ImageAnalysis::class,
                'capabilities' => self::IMAGE_ANALYSIS_CAPABILITIES,
            ],
            'image/logo_detection/api4ai' => [
                'class' => ImageAnalysis::class,
                'capabilities' => self::IMAGE_ANALYSIS_CAPABILITIES,
            ],
            'image/logo_detection/google' => [
                'class' => ImageAnalysis::class,
                'capabilities' => self::IMAGE_ANALYSIS_CAPABILITIES,
            ],
            'image/logo_detection/microsoft' => [
                'class' => ImageAnalysis::class,
                'capabilities' => self::IMAGE_ANALYSIS_CAPABILITIES,
            ],
            'image/logo_detection/openai' => [
                'class' => ImageAnalysis::class,
                'capabilities' => self::IMAGE_ANALYSIS_CAPABILITIES,
            ],
            'image/face_detection/amazon' => [
                'class' => ImageAnalysis::class,
                'capabilities' => self::IMAGE_ANALYSIS_CAPABILITIES,
            ],
            'image/face_detection/api4ai' => [
                'class' => ImageAnalysis::class,
                'capabilities' => self::IMAGE_ANALYSIS_CAPABILITIES,
            ],
            'image/face_detection/google' => [
                'class' => ImageAnalysis::class,
                'capabilities' => self::IMAGE_ANALYSIS_CAPABILITIES,
            ],
            'image/ai_detection/resemble' => [
                'class' => ImageAnalysis::class,
                'capabilities' => self::IMAGE_ANALYSIS_CAPABILITIES,
            ],
            'image/ai_detection/winstonai' => [
                'class' => ImageAnalysis::class,
                'capabilities' => self::IMAGE_ANALYSIS_CAPABILITIES,
            ],
            'image/deepfake_detection/resemble' => [
                'class' => ImageAnalysis::class,
                'capabilities' => self::IMAGE_ANALYSIS_CAPABILITIES,
            ],
            'image/deepfake_detection/sightengine' => [
                'class' => ImageAnalysis::class,
                'capabilities' => self::IMAGE_ANALYSIS_CAPABILITIES,
            ],
            // Image generation expert models
            'image/generation/openai' => [
                'class' => ImageGeneration::class,
                'capabilities' => self::IMAGE_GENERATION_CAPABILITIES,
            ],
            'image/generation/openai/gpt-image-1' => [
                'class' => ImageGeneration::class,
                'capabilities' => self::IMAGE_GENERATION_CAPABILITIES,
            ],
            'image/generation/stabilityai' => [
                'class' => ImageGeneration::class,
                'capabilities' => self::IMAGE_GENERATION_CAPABILITIES,
            ],
        ];

        $this->models = array_merge($defaultModels, $additionalModels);
    }
}
