<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Deepgram\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Deepgram\Contract\AudioNormalizer;
use Symfony\AI\Platform\Bridge\Deepgram\Deepgram;
use Symfony\AI\Platform\Bridge\Deepgram\DeepgramClient;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class DeepgramClientTest extends TestCase
{
    public function testClientCannotPerformActionUsingUnsupportedClient()
    {
        $client = new DeepgramClient(new MockHttpClient());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The model "foo" is not supported, please check the Deepgram API.');
        $this->expectExceptionCode(0);
        $client->request(new Deepgram('foo', [Capability::INPUT_IMAGE]), []);
    }

    public function testClientCanPerformTextToSpeech()
    {
        $httpClient = new MockHttpClient([
            new MockResponse(),
        ]);

        $client = new DeepgramClient($httpClient);
        $client->request(new Deepgram('nova-3', [
            Capability::INPUT_TEXT,
            Capability::TEXT_TO_SPEECH,
            Capability::OUTPUT_AUDIO,
        ]), ['text' => 'foo']);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testClientCanPerformSpeechToText()
    {
        $httpClient = new MockHttpClient([
            new MockResponse(),
        ]);

        $payload = (new AudioNormalizer())->normalize(Audio::fromFile(\dirname(__DIR__).'/Tests/Fixtures/audio.mp3'));

        $client = new DeepgramClient($httpClient);
        $client->request(new Deepgram('zeus', [
            Capability::INPUT_AUDIO,
            Capability::SPEECH_TO_TEXT,
            Capability::OUTPUT_TEXT,
        ]), $payload);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }
}
