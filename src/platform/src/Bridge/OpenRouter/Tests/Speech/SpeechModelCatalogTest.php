<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenRouter\Tests\Speech;

use Symfony\AI\Platform\Bridge\OpenRouter\Speech\SpeechModelCatalog;
use Symfony\AI\Platform\Bridge\OpenRouter\SpeechModel;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Test\ModelCatalogTestCase;

/**
 * @author Tim Lochmüller <tim@fruit-lab.de>
 */
final class SpeechModelCatalogTest extends ModelCatalogTestCase
{
    public static function modelsProvider(): iterable
    {
        $capabilities = [
            Capability::INPUT_TEXT,
            Capability::OUTPUT_AUDIO,
            Capability::TEXT_TO_SPEECH,
        ];

        yield 'canopylabs/orpheus-3b-0.1-ft' => [
            'canopylabs/orpheus-3b-0.1-ft',
            SpeechModel::class,
            $capabilities,
        ];

        yield 'google/gemini-3.1-flash-tts-preview' => [
            'google/gemini-3.1-flash-tts-preview',
            SpeechModel::class,
            $capabilities,
        ];

        yield 'hexgrad/kokoro-82m' => [
            'hexgrad/kokoro-82m',
            SpeechModel::class,
            $capabilities,
        ];

        yield 'mistralai/voxtral-mini-tts-2603' => [
            'mistralai/voxtral-mini-tts-2603',
            SpeechModel::class,
            $capabilities,
        ];

        yield 'openai/gpt-4o-mini-tts-2025-12-15' => [
            'openai/gpt-4o-mini-tts-2025-12-15',
            SpeechModel::class,
            $capabilities,
        ];

        yield 'sesame/csm-1b' => [
            'sesame/csm-1b',
            SpeechModel::class,
            $capabilities,
        ];

        yield 'zyphra/zonos-v0.1-hybrid' => [
            'zyphra/zonos-v0.1-hybrid',
            SpeechModel::class,
            $capabilities,
        ];

        yield 'zyphra/zonos-v0.1-transformer' => [
            'zyphra/zonos-v0.1-transformer',
            SpeechModel::class,
            $capabilities,
        ];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new SpeechModelCatalog();
    }
}
