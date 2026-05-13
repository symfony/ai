<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter\Speech;

use Symfony\AI\Platform\Bridge\OpenRouter\SpeechModel;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * @author Tim Lochmüller <tim@fruit-lab.de>
 */
final class SpeechModelCatalog extends AbstractModelCatalog
{
    /**
     * @var array<string, array{class: class-string, capabilities: list<Capability>}>
     */
    protected array $models = [
        'canopylabs/orpheus-3b-0.1-ft' => [
            'class' => SpeechModel::class,
            'capabilities' => [
                Capability::INPUT_TEXT,
                Capability::OUTPUT_AUDIO,
                Capability::TEXT_TO_SPEECH,
            ],
        ],
        'google/gemini-3.1-flash-tts-preview' => [
            'class' => SpeechModel::class,
            'capabilities' => [
                Capability::INPUT_TEXT,
                Capability::OUTPUT_AUDIO,
                Capability::TEXT_TO_SPEECH,
            ],
        ],
        'hexgrad/kokoro-82m' => [
            'class' => SpeechModel::class,
            'capabilities' => [
                Capability::INPUT_TEXT,
                Capability::OUTPUT_AUDIO,
                Capability::TEXT_TO_SPEECH,
            ],
        ],
        'mistralai/voxtral-mini-tts-2603' => [
            'class' => SpeechModel::class,
            'capabilities' => [
                Capability::INPUT_TEXT,
                Capability::OUTPUT_AUDIO,
                Capability::TEXT_TO_SPEECH,
            ],
        ],
        'openai/gpt-4o-mini-tts-2025-12-15' => [
            'class' => SpeechModel::class,
            'capabilities' => [
                Capability::INPUT_TEXT,
                Capability::OUTPUT_AUDIO,
                Capability::TEXT_TO_SPEECH,
            ],
        ],
        'sesame/csm-1b' => [
            'class' => SpeechModel::class,
            'capabilities' => [
                Capability::INPUT_TEXT,
                Capability::OUTPUT_AUDIO,
                Capability::TEXT_TO_SPEECH,
            ],
        ],
        'zyphra/zonos-v0.1-hybrid' => [
            'class' => SpeechModel::class,
            'capabilities' => [
                Capability::INPUT_TEXT,
                Capability::OUTPUT_AUDIO,
                Capability::TEXT_TO_SPEECH,
            ],
        ],
        'zyphra/zonos-v0.1-transformer' => [
            'class' => SpeechModel::class,
            'capabilities' => [
                Capability::INPUT_TEXT,
                Capability::OUTPUT_AUDIO,
                Capability::TEXT_TO_SPEECH,
            ],
        ],
    ];
}
