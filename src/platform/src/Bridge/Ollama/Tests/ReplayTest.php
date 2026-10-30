<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama\Tests;

use Symfony\AI\Platform\Bridge\Ollama\Factory;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Test\Replay\AbstractBridgeReplayTestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Replays recorded Ollama speech endpoint interactions through the real bridge
 * pipeline. The audio body is elided to a metadata stub in the cassette, so the
 * assertions cover the converter's plumbing - result type and error mapping -
 * rather than the audio content.
 */
final class ReplayTest extends AbstractBridgeReplayTestCase
{
    public function testSpeech()
    {
        $platform = $this->platformForCassette('speech');

        $result = $platform->invoke('phonon', 'Hello from Ollama.', [
            'voice' => 'Zoey',
            'response_format' => 'wav',
        ]);

        $binary = $result->getResult();
        $this->assertInstanceOf(BinaryResult::class, $binary);
        $this->assertSame('audio/wav', $binary->getMimeType());
        $this->assertNotSame('', $result->asBinary());
    }

    public function testBadRequest()
    {
        $platform = $this->platformForCassette('bad_request');

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('voice "nope" not found; available voices: default');

        $platform->invoke('phonon', 'Hello', ['voice' => 'nope'])->asBinary();
    }

    protected function createPlatform(HttpClientInterface $httpClient): PlatformInterface
    {
        return Factory::createPlatform('http://127.0.0.1:11434', httpClient: $httpClient);
    }

    protected function cassetteDirectory(): string
    {
        return __DIR__.'/Fixtures/cassettes/text_to_speech';
    }
}
