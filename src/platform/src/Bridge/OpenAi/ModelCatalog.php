<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: string, label: string, capabilities: list<Capability>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        $defaultModels = [
            'gpt-3.5-turbo' => [
                'class' => Gpt::class,
                'label' => 'GPT-3.5 Turbo',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'gpt-3.5-turbo-instruct' => [
                'class' => Gpt::class,
                'label' => 'GPT-3.5 Turbo Instruct',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'gpt-4' => [
                'class' => Gpt::class,
                'label' => 'GPT-4',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'gpt-4-turbo' => [
                'class' => Gpt::class,
                'label' => 'GPT-4 Turbo',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                ],
            ],
            'gpt-4o' => [
                'class' => Gpt::class,
                'label' => 'GPT-4o',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_PDF,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'gpt-4o-mini' => [
                'class' => Gpt::class,
                'label' => 'GPT-4o Mini',
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
            'gpt-4o-audio-preview' => [
                'class' => Gpt::class,
                'label' => 'GPT-4o Audio',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_STRUCTURED,
                    // Audio is unsupported temporarily due to migration to Responses API;
                    // Capability will be reintroduced when Responses API supports audio ("coming soon")
                    // See: https://platform.openai.com/docs/guides/migrate-to-responses#responses-benefits
                    // Capability::INPUT_AUDIO,
                ],
            ],
            'o3' => [
                'class' => Gpt::class,
                'label' => 'O3',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'o3-mini' => [
                'class' => Gpt::class,
                'label' => 'O3 Mini',
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
            'o3-mini-high' => [
                'class' => Gpt::class,
                'label' => 'O3 Mini High',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'gpt-4.5-preview' => [
                'class' => Gpt::class,
                'label' => 'GPT-4.5 Preview',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_PDF,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'gpt-4.1' => [
                'class' => Gpt::class,
                'label' => 'GPT-4.1',
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
            'gpt-4.1-mini' => [
                'class' => Gpt::class,
                'label' => 'GPT-4.1 Mini',
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
            'gpt-4.1-nano' => [
                'class' => Gpt::class,
                'label' => 'GPT-4.1 Nano',
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
            'gpt-5' => [
                'class' => Gpt::class,
                'label' => 'GPT-5',
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
            'gpt-5-chat-latest' => [
                'class' => Gpt::class,
                'label' => 'GPT-5 Chat',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                ],
            ],
            'gpt-5-mini' => [
                'class' => Gpt::class,
                'label' => 'GPT-5 Mini',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'gpt-5-nano' => [
                'class' => Gpt::class,
                'label' => 'GPT-5 Nano',
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'gpt-5.2' => [
                'class' => Gpt::class,
                'label' => 'GPT-5.2',
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::OUTPUT_TEXT,
                    Capability::TOOL_CALLING,
                ],
            ],
            'text-embedding-ada-002' => [
                'class' => Embeddings::class,
                'label' => 'Text Embedding Ada 002 (Embeddings)',
                'capabilities' => [Capability::INPUT_TEXT, Capability::EMBEDDINGS],
            ],
            'text-embedding-3-large' => [
                'class' => Embeddings::class,
                'label' => 'Text Embedding 3 Large (Embeddings)',
                'capabilities' => [Capability::INPUT_TEXT, Capability::EMBEDDINGS],
            ],
            'text-embedding-3-small' => [
                'class' => Embeddings::class,
                'label' => 'Text Embedding 3 Small (Embeddings)',
                'capabilities' => [Capability::INPUT_TEXT, Capability::EMBEDDINGS],
            ],
            'tts-1' => [
                'class' => TextToSpeech::class,
                'label' => 'TTS-1 (TTS)',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_AUDIO,
                ],
            ],
            'tts-1-hd' => [
                'class' => TextToSpeech::class,
                'label' => 'TTS-1 HD (TTS)',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_AUDIO,
                ],
            ],
            'gpt-4o-mini-tts' => [
                'class' => TextToSpeech::class,
                'label' => 'GPT-4o Mini TTS (TTS)',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_AUDIO,
                ],
            ],
            'whisper-1' => [
                'class' => Whisper::class,
                'label' => 'Whisper (STT)',
                'capabilities' => [
                    Capability::INPUT_AUDIO,
                    Capability::OUTPUT_TEXT,
                ],
            ],
            'dall-e-2' => [
                'class' => DallE::class,
                'label' => 'DALL-E 2',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_IMAGE,
                ],
            ],
            'dall-e-3' => [
                'class' => DallE::class,
                'label' => 'DALL-E 3',
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_IMAGE,
                ],
            ],
        ];

        $this->models = array_merge($defaultModels, $additionalModels);
    }
}
