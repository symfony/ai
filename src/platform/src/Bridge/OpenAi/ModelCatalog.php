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
     * @param array<string, array{class: string, capabilities: list<Capability>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        // STATIC LIST START
        // This list is generated from external metadata. Run dev/update-model-catalogs.php to refresh it.
        $defaultModels = [
            'chatgpt-image-latest' => [
                'class' => Gpt::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::OUTPUT_IMAGE,
                ],
            ],
            'gpt-3.5-turbo' => [
                'class' => Gpt::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'gpt-3.5-turbo-instruct' => [
                'class' => Gpt::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'gpt-4' => [
                'class' => Gpt::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'gpt-4-turbo' => [
                'class' => Gpt::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                ],
            ],
            'gpt-4.1' => [
                'class' => Gpt::class,
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
            'gpt-4.5-preview' => [
                'class' => Gpt::class,
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
            'gpt-4o' => [
                'class' => Gpt::class,
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
            'gpt-4o-2024-05-13' => [
                'class' => Gpt::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'gpt-4o-2024-08-06' => [
                'class' => Gpt::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'gpt-4o-2024-11-20' => [
                'class' => Gpt::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'gpt-4o-audio-preview' => [
                'class' => Gpt::class,
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
            'gpt-4o-mini' => [
                'class' => Gpt::class,
                'capabilities' => [
                    Capability::BATCH,
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_STRUCTURED,
                ],
            ],
            'gpt-4o-mini-tts' => [
                'class' => TextToSpeech::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_AUDIO,
                ],
            ],
            'gpt-5' => [
                'class' => Gpt::class,
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
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                ],
            ],
            'gpt-5-codex' => [
                'class' => Gpt::class,
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
            'gpt-5-mini' => [
                'class' => Gpt::class,
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
            'gpt-5-pro' => [
                'class' => Gpt::class,
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
            'gpt-5.1' => [
                'class' => Gpt::class,
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
            'gpt-5.1-chat-latest' => [
                'class' => Gpt::class,
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
            'gpt-5.1-codex' => [
                'class' => Gpt::class,
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
            'gpt-5.1-codex-max' => [
                'class' => Gpt::class,
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
            'gpt-5.1-codex-mini' => [
                'class' => Gpt::class,
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
            'gpt-5.2' => [
                'class' => Gpt::class,
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
            'gpt-5.2-chat-latest' => [
                'class' => Gpt::class,
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
            'gpt-5.2-codex' => [
                'class' => Gpt::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::THINKING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                ],
            ],
            'gpt-5.2-pro' => [
                'class' => Gpt::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::THINKING,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'gpt-5.3-chat-latest' => [
                'class' => Gpt::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'gpt-5.3-codex' => [
                'class' => Gpt::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::THINKING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                ],
            ],
            'gpt-5.3-codex-spark' => [
                'class' => Gpt::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::THINKING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                ],
            ],
            'gpt-5.4' => [
                'class' => Gpt::class,
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
            'gpt-5.4-mini' => [
                'class' => Gpt::class,
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
            'gpt-5.4-nano' => [
                'class' => Gpt::class,
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
            'gpt-5.4-pro' => [
                'class' => Gpt::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_TEXT,
                    Capability::TOOL_CALLING,
                ],
            ],
            'gpt-5.5' => [
                'class' => Gpt::class,
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
            'gpt-5.5-pro' => [
                'class' => Gpt::class,
                'capabilities' => [
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_MESSAGES,
                    Capability::INPUT_PDF,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::OUTPUT_TEXT,
                    Capability::TOOL_CALLING,
                ],
            ],
            'gpt-image-1' => [
                'class' => Image::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_IMAGE,
                ],
            ],
            'gpt-image-1-mini' => [
                'class' => Image::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_IMAGE,
                ],
            ],
            'gpt-image-1.5' => [
                'class' => Image::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_IMAGE,
                ],
            ],
            'gpt-image-2' => [
                'class' => Image::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_IMAGE,
                ],
            ],
            'o1' => [
                'class' => Gpt::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::THINKING,
                    Capability::INPUT_IMAGE,
                    Capability::INPUT_PDF,
                ],
            ],
            'o1-mini' => [
                'class' => Gpt::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::OUTPUT_STRUCTURED,
                    Capability::THINKING,
                ],
            ],
            'o1-preview' => [
                'class' => Gpt::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::THINKING,
                ],
            ],
            'o1-pro' => [
                'class' => Gpt::class,
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
            'o3' => [
                'class' => Gpt::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'o3-deep-research' => [
                'class' => Gpt::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::THINKING,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'o3-mini' => [
                'class' => Gpt::class,
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
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                ],
            ],
            'o3-pro' => [
                'class' => Gpt::class,
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
            'o4-mini' => [
                'class' => Gpt::class,
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
            'o4-mini-deep-research' => [
                'class' => Gpt::class,
                'capabilities' => [
                    Capability::INPUT_MESSAGES,
                    Capability::OUTPUT_TEXT,
                    Capability::OUTPUT_STREAMING,
                    Capability::TOOL_CALLING,
                    Capability::THINKING,
                    Capability::INPUT_IMAGE,
                ],
            ],
            'text-embedding-3-large' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'text-embedding-3-small' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'text-embedding-ada-002' => [
                'class' => Embeddings::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::EMBEDDINGS,
                ],
            ],
            'tts-1' => [
                'class' => TextToSpeech::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_AUDIO,
                ],
            ],
            'tts-1-hd' => [
                'class' => TextToSpeech::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_AUDIO,
                ],
            ],
            'whisper-1' => [
                'class' => Whisper::class,
                'capabilities' => [
                    Capability::INPUT_AUDIO,
                    Capability::OUTPUT_TEXT,
                ],
            ],
        ];
        // STATIC LIST END

        $this->models = array_merge($defaultModels, $additionalModels);
    }
}
