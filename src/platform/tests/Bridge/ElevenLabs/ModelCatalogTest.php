<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\ElevenLabs;

use Symfony\AI\Platform\Bridge\ElevenLabs\ElevenLabs;
use Symfony\AI\Platform\Bridge\ElevenLabs\ModelCatalog;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Test\ModelCatalogTestCase;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelCatalogTest extends ModelCatalogTestCase
{
    public static function modelsProvider(): iterable
    {
        yield 'eleven_v3' => ['eleven_v3', ElevenLabs::class, [Capability::INPUT_TEXT, Capability::OUTPUT_AUDIO, Capability::TEXT_TO_SPEECH]];
        yield 'eleven_ttv_v3' => ['eleven_ttv_v3', ElevenLabs::class, [Capability::INPUT_TEXT, Capability::OUTPUT_AUDIO, Capability::TEXT_TO_SPEECH]];
        yield 'eleven_multilingual_v2' => ['eleven_multilingual_v2', ElevenLabs::class, [Capability::INPUT_TEXT, Capability::OUTPUT_AUDIO, Capability::TEXT_TO_SPEECH]];
        yield 'eleven_flash_v2_5' => ['eleven_flash_v2_5', ElevenLabs::class, [Capability::INPUT_TEXT, Capability::OUTPUT_AUDIO, Capability::TEXT_TO_SPEECH]];
        yield 'eleven_flashv2' => ['eleven_flashv2', ElevenLabs::class, [Capability::INPUT_TEXT, Capability::OUTPUT_AUDIO, Capability::TEXT_TO_SPEECH]];
        yield 'eleven_turbo_v2_5' => ['eleven_turbo_v2_5', ElevenLabs::class, [Capability::INPUT_TEXT, Capability::OUTPUT_AUDIO, Capability::TEXT_TO_SPEECH]];
        yield 'eleven_turbo_v2' => ['eleven_turbo_v2', ElevenLabs::class, [Capability::INPUT_TEXT, Capability::OUTPUT_AUDIO, Capability::TEXT_TO_SPEECH]];
        yield 'eleven_multilingual_sts_v2' => ['eleven_multilingual_sts_v2', ElevenLabs::class, [Capability::INPUT_TEXT, Capability::OUTPUT_AUDIO, Capability::TEXT_TO_SPEECH]];
        yield 'eleven_multilingual_ttv_v2' => ['eleven_multilingual_ttv_v2', ElevenLabs::class, [Capability::INPUT_TEXT, Capability::OUTPUT_AUDIO, Capability::TEXT_TO_SPEECH]];
        yield 'eleven_english_sts_v2' => ['eleven_english_sts_v2', ElevenLabs::class, [Capability::INPUT_TEXT, Capability::OUTPUT_AUDIO, Capability::TEXT_TO_SPEECH]];
        yield 'scribe_v1' => ['scribe_v1', ElevenLabs::class, [Capability::INPUT_AUDIO, Capability::OUTPUT_TEXT, Capability::SPEECH_TO_TEXT]];
        yield 'scribe_v1_experimental' => ['scribe_v1_experimental', ElevenLabs::class, [Capability::INPUT_AUDIO, Capability::OUTPUT_TEXT, Capability::SPEECH_TO_TEXT]];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
