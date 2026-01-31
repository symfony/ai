<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\MiniMax\Tests;

use Symfony\AI\Platform\Bridge\MiniMax\MiniMax;
use Symfony\AI\Platform\Bridge\MiniMax\ModelCatalog;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Test\ModelCatalogTestCase;

final class ModelCatalogTest extends ModelCatalogTestCase
{
    public static function modelsProvider(): iterable
    {
        yield 'M2-her' => ['M2-her', MiniMax::class, [Capability::INPUT_MESSAGES]];
        yield 'speech-2.8-hd' => ['speech-2.8-hd', MiniMax::class, [Capability::TEXT_TO_SPEECH, Capability::TEXT_TO_SPEECH_ASYNC]];
        yield 'speech-2.8-turbo' => ['speech-2.8-turbo', MiniMax::class, [Capability::TEXT_TO_SPEECH, Capability::TEXT_TO_SPEECH_ASYNC]];
        yield 'speech-2.6-hd' => ['speech-2.6-hd', MiniMax::class, [Capability::TEXT_TO_SPEECH, Capability::TEXT_TO_SPEECH_ASYNC]];
        yield 'speech-2.6-turbo' => ['speech-2.6-turbo', MiniMax::class, [Capability::TEXT_TO_SPEECH, Capability::TEXT_TO_SPEECH_ASYNC]];
        yield 'speech-02-hd' => ['speech-02-hd', MiniMax::class, [Capability::TEXT_TO_SPEECH, Capability::TEXT_TO_SPEECH_ASYNC]];
        yield 'speech-02-turbo' => ['speech-02-turbo', MiniMax::class, [Capability::TEXT_TO_SPEECH, Capability::TEXT_TO_SPEECH_ASYNC]];
        yield 'speech-01-hd' => ['speech-01-hd', MiniMax::class, [Capability::TEXT_TO_SPEECH, Capability::TEXT_TO_SPEECH_ASYNC]];
        yield 'speech-01-turbo' => ['speech-01-turbo', MiniMax::class, [Capability::TEXT_TO_SPEECH]];
        yield 'MiniMax-Hailuo-2.3' => ['MiniMax-Hailuo-2.3', MiniMax::class, [Capability::TEXT_TO_VIDEO, Capability::IMAGE_TO_VIDEO]];
        yield 'MiniMax-Hailuo-2.3-Fast' => ['MiniMax-Hailuo-2.3-Fast', MiniMax::class, [Capability::IMAGE_TO_VIDEO]];
        yield 'MiniMax-Hailuo-02' => ['MiniMax-Hailuo-02', MiniMax::class, [Capability::TEXT_TO_VIDEO, Capability::IMAGE_TO_VIDEO, Capability::VIDEO_FRAME_TO_FRAME]];
        yield 'T2V-01-Director' => ['T2V-01-Director', MiniMax::class, [Capability::TEXT_TO_VIDEO, Capability::IMAGE_TO_VIDEO]];
        yield 'I2V-01-live' => ['I2V-01-live', MiniMax::class, [Capability::IMAGE_TO_VIDEO]];
        yield 'T2V-01' => ['T2V-01', MiniMax::class, [Capability::TEXT_TO_VIDEO, Capability::IMAGE_TO_VIDEO]];
        yield 'S2V-01' => ['S2V-01', MiniMax::class, [Capability::VIDEO_WITH_SUBJECT]];
        yield 'image-01' => ['image-01', MiniMax::class, [Capability::TEXT_TO_IMAGE, Capability::IMAGE_TO_IMAGE]];
        yield 'image-01-live' => ['image-01-live', MiniMax::class, [Capability::IMAGE_TO_IMAGE]];
    }

    protected function createModelCatalog(): ModelCatalogInterface
    {
        return new ModelCatalog();
    }
}
