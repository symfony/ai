<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Mistral\Factory;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class TextToSpeechTest extends TestCase
{
    public function testItRoutesTheModelNameToTheSpeechEndpoint()
    {
        $urls = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$urls): JsonMockResponse {
            $urls[] = $url;

            return new JsonMockResponse(['audio_data' => base64_encode('mp3-bytes')]);
        });

        $result = Factory::createPlatform('test-key', $httpClient)
            ->invoke('voxtral-mini-tts-3-0', new Text('Hello world'), ['voice' => 'gb_jane_neutral']);

        $this->assertSame('mp3-bytes', $result->asBinary());
        $this->assertSame(['https://api.mistral.ai/v1/audio/speech'], $urls);
    }
}
