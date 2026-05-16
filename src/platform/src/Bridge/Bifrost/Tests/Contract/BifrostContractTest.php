<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bifrost\Tests\Contract;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Bifrost\Audio\TranscriptionModel;
use Symfony\AI\Platform\Bridge\Bifrost\Contract\BifrostContract;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Content\Audio;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class BifrostContractTest extends TestCase
{
    public function testCreateReturnsContractInstance()
    {
        $this->assertInstanceOf(Contract::class, BifrostContract::create());
    }

    public function testAudioContentIsNormalizedForTranscriptionModel()
    {
        $audio = Audio::fromFile(\dirname(__DIR__, 7).'/fixtures/audio.mp3');

        $payload = BifrostContract::create()->createRequestPayload(
            new TranscriptionModel('openai/whisper-1'),
            $audio,
        );

        $this->assertIsArray($payload);
        $this->assertSame('openai/whisper-1', $payload['model']);
        $this->assertIsResource($payload['file']);
    }
}
