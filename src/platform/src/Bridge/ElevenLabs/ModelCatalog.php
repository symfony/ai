<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ElevenLabs;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelCatalog extends AbstractModelCatalog
{
    /**
     * @param array<string, array{class: class-string, capabilities: list<string>}> $additionalModels
     */
    public function __construct(array $additionalModels = [])
    {
        $defaultModels = [
            'eleven_v3' => [
                'class' => ElevenLabs::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_AUDIO,
                    Capability::TEXT_TO_SPEECH,
                ],
            ],
            'eleven_ttv_v3' => [
                'class' => ElevenLabs::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_AUDIO,
                    Capability::TEXT_TO_SPEECH,
                ],
            ],
            'eleven_multilingual_v2' => [
                'class' => ElevenLabs::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_AUDIO,
                    Capability::TEXT_TO_SPEECH,
                ],
            ],
            'eleven_flash_v2_5' => [
                'class' => ElevenLabs::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_AUDIO,
                    Capability::TEXT_TO_SPEECH,
                ],
            ],
            'eleven_flashv2' => [
                'class' => ElevenLabs::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_AUDIO,
                    Capability::TEXT_TO_SPEECH,
                ],
            ],
            'eleven_turbo_v2_5' => [
                'class' => ElevenLabs::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_AUDIO,
                    Capability::TEXT_TO_SPEECH,
                ],
            ],
            'eleven_turbo_v2' => [
                'class' => ElevenLabs::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_AUDIO,
                    Capability::TEXT_TO_SPEECH,
                ],
            ],
            'eleven_multilingual_sts_v2' => [
                'class' => ElevenLabs::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_AUDIO,
                    Capability::TEXT_TO_SPEECH,
                ],
            ],
            'eleven_multilingual_ttv_v2' => [
                'class' => ElevenLabs::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_AUDIO,
                    Capability::TEXT_TO_SPEECH,
                ],
            ],
            'eleven_english_sts_v2' => [
                'class' => ElevenLabs::class,
                'capabilities' => [
                    Capability::INPUT_TEXT,
                    Capability::OUTPUT_AUDIO,
                    Capability::TEXT_TO_SPEECH,
                ],
            ],
            'scribe_v1' => [
                'class' => ElevenLabs::class,
                'capabilities' => [
                    Capability::INPUT_AUDIO,
                    Capability::OUTPUT_TEXT,
                    Capability::SPEECH_TO_TEXT,
                ],
            ],
            'scribe_v1_experimental' => [
                'class' => ElevenLabs::class,
                'capabilities' => [
                    Capability::INPUT_AUDIO,
                    Capability::OUTPUT_TEXT,
                    Capability::SPEECH_TO_TEXT,
                ],
            ],
        ];

        $this->models = array_merge($defaultModels, $additionalModels);
    }
}
