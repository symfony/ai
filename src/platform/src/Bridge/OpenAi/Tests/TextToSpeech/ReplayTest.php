<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests\TextToSpeech;

use Symfony\AI\Platform\Bridge\OpenAi\Factory;
use Symfony\AI\Platform\Bridge\OpenAi\TextToSpeech\Voice;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Test\Replay\AbstractBridgeReplayTestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Replays recorded OpenAI speech endpoint interactions through the real bridge
 * pipeline. The audio body is elided to a metadata stub in the cassette, so the
 * assertions cover the converter's plumbing - result type, status handling and
 * error mapping - rather than the audio content.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ReplayTest extends AbstractBridgeReplayTestCase
{
    public function testSpeech()
    {
        $platform = $this->platformForCassette('speech');

        $result = $platform->invoke('gpt-4o-mini-tts', 'Today is a wonderful day to build something people love!', [PlatformOptions::PROVIDER_OPTIONS => ['voice' => Voice::CORAL]]);

        $this->assertInstanceOf(BinaryResult::class, $result->getResult());
        $this->assertNotSame('', $result->asBinary());
    }

    public function testBadRequest()
    {
        $platform = $this->platformForCassette('bad_request');

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Invalid value: nope.');

        $platform->invoke('gpt-4o-mini-tts', 'Hello', [PlatformOptions::PROVIDER_OPTIONS => ['voice' => 'nope']])->asBinary();
    }

    protected function createPlatform(HttpClientInterface $httpClient): PlatformInterface
    {
        return Factory::createPlatform('sk-test-api-key', $httpClient);
    }

    protected function cassetteDirectory(): string
    {
        return __DIR__.'/../Fixtures/cassettes/text_to_speech';
    }
}
