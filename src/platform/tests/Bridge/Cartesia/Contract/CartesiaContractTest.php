<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Cartesia\Contract;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Cartesia\Cartesia;
use Symfony\AI\Platform\Bridge\Cartesia\Contract\CartesiaContract;
use Symfony\AI\Platform\Message\Content\Audio;

final class CartesiaContractTest extends TestCase
{
    public function testItCanCreatePayloadWithAudio()
    {
        $audio = Audio::fromFile(\dirname(__DIR__, 6).'/fixtures/audio.mp3');

        $contract = CartesiaContract::create();

        $payload = $contract->createRequestPayload(new Cartesia('ink-whisper'), $audio);

        $this->assertSame([
            'type' => 'input_audio',
            'input_audio' => [
                'data' => $audio->asBase64(),
                'path' => $audio->asPath(),
                'format' => 'mp3',
            ],
        ], $payload);
    }
}
