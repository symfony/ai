<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Together\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Together\Contract\TogetherContract;
use Symfony\AI\Platform\Bridge\Together\Together;
use Symfony\AI\Platform\Message\Content\Audio;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class TogetherContractTest extends TestCase
{
    public function testItNormalizesAudioForSpeechToText()
    {
        $audio = Audio::fromFile(\dirname(__DIR__, 7).'/fixtures/audio.mp3');

        $contract = TogetherContract::create();

        $payload = $contract->createRequestPayload(new Together('openai/whisper-large-v3'), $audio);

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
