<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: string, capabilities: list<Capability>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        $defaultModels = [
            'gemini-3-pro-preview' => [
                'class' => Gemini::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_PDF,
                    Capability::INPUT_VIDEO,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
                    Capability::THINKING,
                ],
            ],
            'gemini-3-pro-image-preview' => [
                'class' => Gemini::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::THINKING,
                ],
            ],
            'gemini-2.5-flash-image' => [
                'class' => Gemini::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_IMAGE,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'gemini-2.5-flash' => [
                'class' => Gemini::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_PDF,
                    Capability::INPUT_VIDEO,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
                    Capability::THINKING,
                ],
            ],
            'gemini-2.5-pro' => [
                'class' => Gemini::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_PDF,
                    Capability::INPUT_VIDEO,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
                    Capability::THINKING,
                ],
            ],
            'gemini-2.5-flash-lite-preview-09-2025' => [
                'class' => Gemini::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_PDF,
                    Capability::INPUT_VIDEO,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
                    Capability::THINKING,
                ],
            ],
            'gemini-2.5-flash-lite' => [
                'class' => Gemini::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_PDF,
                    Capability::INPUT_VIDEO,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
                    Capability::THINKING,
                ],
            ],
            // 01/06/2026
            // https://ai.google.dev/gemini-api/docs/changelog?hl=en#02-18-2026
            'gemini-2.0-flash' => [
                'class' => Gemini::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_VIDEO,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            // TTS
            'gemini-2.5-flash-native-audio-preview-12-2025' => [
                'class' => Gemini::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_VIDEO,
                    Capability::INPUT_AUDIO,
                    Capability::OUTPUT_AUDIO,
                    Capability::TEXT_TO_SPEECH,
                ],
            ],
            'gemini-2.5-flash-preview-tts' => [
                'class' => Gemini::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_AUDIO,
                    Capability::TEXT_TO_SPEECH,
                ],
            ],
            'gemini-2.5-pro-preview-tts' => [
                'class' => Gemini::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_AUDIO,
                    Capability::TEXT_TO_SPEECH,
                ],
            ],
            // Embeddings
            'gemini-embedding-001' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS
                ],
            ],
        ];

        $this->models = array_merge($defaultModels, $additionalModels);
    }
}
