<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\ElevenLabs\Contract;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\ElevenLabs\Contract\ElevenLabsContract;
use Symfony\AI\Platform\Bridge\ElevenLabs\ElevenLabs;
use Symfony\AI\Platform\Message\Content\Audio;

final class ElevenLabsContractTest extends TestCase
{
    public function testItCanCreatePayloadWithAudio()
    {
        $audio = Audio::fromFile(\dirname(__DIR__, 6).'/fixtures/audio.mp3');

        $contract = ElevenLabsContract::create();

        $payload = $contract->createRequestPayload(new ElevenLabs('eleven_multilingual_v2'), $audio);

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
