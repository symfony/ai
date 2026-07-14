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
        // STATIC LIST START
        // This list is generated from external metadata. Run dev/update-model-catalogs.php to refresh it.
        $defaultModels = [
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
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
                ],
            ],
            'gemini-2.0-flash-lite' => [
                'class' => Gemini::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::INPUT_AUDIO,
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
            'gemini-2.5-pro-preview-tts' => [
                'class' => Gemini::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_AUDIO,
                    Capability::TEXT_TO_SPEECH,
                ],
            ],
            'gemini-3-flash-preview' => [
                'class' => Gemini::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::TOOL_CALLING,
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
            'gemini-3.1-flash-image-preview' => [
                'class' => Gemini::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::THINKING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_IMAGE,
                ],
            ],
            'gemini-3.1-flash-lite' => [
                'class' => Gemini::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::THINKING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::INPUT_AUDIO,
                ],
            ],
            'gemini-3.1-flash-lite-preview' => [
                'class' => Gemini::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::THINKING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::INPUT_AUDIO,
                ],
            ],
            'gemini-3.1-pro-preview' => [
                'class' => Gemini::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_AUDIO,
                    Capability::INPUT_PDF,
                    Capability::INPUT_VIDEO,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::OUTPUT_TEXT,
                    Capability::TOOL_CALLING,
                    Capability::THINKING,
                ],
            ],
            'gemini-3.1-pro-preview-customtools' => [
                'class' => Gemini::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::THINKING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::INPUT_AUDIO,
                ],
            ],
            'gemini-3.5-flash' => [
                'class' => Gemini::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::THINKING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::INPUT_AUDIO,
                ],
            ],
            'gemini-embedding-001' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'gemini-flash-latest' => [
                'class' => Gemini::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::THINKING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::INPUT_AUDIO,
                ],
            ],
            'gemini-flash-lite-latest' => [
                'class' => Gemini::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::THINKING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::INPUT_AUDIO,
                ],
            ],
            'gemini-omni-flash-preview' => [
                'class' => Gemini::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::THINKING,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'gemma-4-26b-a4b-it' => [
                'class' => Gemini::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::THINKING,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'gemma-4-31b-it' => [
                'class' => Gemini::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::THINKING,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'gemma-4-E2B-it' => [
                'class' => Gemini::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::THINKING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_AUDIO,
                ],
            ],
            'gemma-4-E4B-it' => [
                'class' => Gemini::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::THINKING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_AUDIO,
                ],
            ],
        ];
        // STATIC LIST END

        $this->models = array_merge($defaultModels, $additionalModels);
    }
}
