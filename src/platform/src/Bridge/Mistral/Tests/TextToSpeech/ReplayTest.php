<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Tests\TextToSpeech;

use Symfony\AI\Platform\Bridge\Mistral\Factory;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Test\Replay\AbstractBridgeReplayTestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ReplayTest extends AbstractBridgeReplayTestCase
{
    public function testSpeech()
    {
        $platform = $this->platformForCassette('speech');

        $result = $platform->invoke('voxtral-mini-tts-3-0', 'Hello world', ['voice' => 'gb_jane_neutral']);

        $this->assertInstanceOf(BinaryResult::class, $result->getResult());
        $this->assertSame('audio/mpeg', $result->getResult()->getMimeType());
        $this->assertStringStartsWith('ID3', $result->asBinary());
    }

    public function testUnknownVoice()
    {
        $platform = $this->platformForCassette('unknown_voice');

        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage("Voice 'nope' not found.");

        $platform->invoke('voxtral-mini-tts-3-0', 'Hello', ['voice' => 'nope'])->asBinary();
    }

    public function testBadRequest()
    {
        $platform = $this->platformForCassette('bad_request');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected response code 422');

        $platform->invoke('voxtral-mini-tts-3-0', 'Hello', ['voice' => 'gb_jane_neutral', 'response_format' => 'nope'])->asBinary();
    }

    protected function createPlatform(HttpClientInterface $httpClient): PlatformInterface
    {
        return Factory::createPlatform('test-api-key', $httpClient);
    }

    protected function cassetteDirectory(): string
    {
        return __DIR__.'/../Fixtures/cassettes/text_to_speech';
    }
}
