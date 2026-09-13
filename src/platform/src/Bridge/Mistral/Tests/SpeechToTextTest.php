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
use Symfony\AI\Platform\Bridge\Mistral\SpeechToText;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * "voxtral-mini-latest" is both a chat and a transcription model at Mistral, and the catalog keeps
 * it mapped to the chat model. Transcription is opt-in through an explicit {@see SpeechToText}
 * instance, so these tests pin which endpoint each of the two invocation styles reaches.
 */
final class SpeechToTextTest extends TestCase
{
    public function testItExtendsModel()
    {
        $model = new SpeechToText('voxtral-mini-latest');

        $this->assertInstanceOf(Model::class, $model);
        $this->assertSame('voxtral-mini-latest', $model->getName());
    }

    public function testItRoutesAnExplicitSpeechToTextModelToTheTranscriptionEndpoint()
    {
        $urls = [];
        $platform = $this->createPlatform($urls, new JsonMockResponse(['text' => 'Hello from the fixture.']));

        $result = $platform->invoke(
            new SpeechToText('voxtral-mini-latest'),
            Audio::fromFile(\dirname(__DIR__, 6).'/fixtures/audio.mp3'),
            ['language' => 'en'],
        );

        $this->assertSame('Hello from the fixture.', $result->asText());
        $this->assertSame(['https://api.mistral.ai/v1/audio/transcriptions'], $urls);
    }

    public function testItRoutesThePlainModelNameToTheChatEndpoint()
    {
        $urls = [];
        $platform = $this->createPlatform($urls, new JsonMockResponse([
            'choices' => [[
                'index' => 0,
                'message' => ['role' => 'assistant', 'content' => 'Hello from chat.'],
                'finish_reason' => 'stop',
            ]],
        ]));

        $result = $platform->invoke(
            'voxtral-mini-latest',
            new MessageBag(Message::ofUser(
                'What is said in this audio?',
                Audio::fromFile(\dirname(__DIR__, 6).'/fixtures/audio.mp3'),
            )),
        );

        $this->assertSame('Hello from chat.', $result->asText());
        $this->assertSame(['https://api.mistral.ai/v1/chat/completions'], $urls);
    }

    /**
     * @param list<string> $urls Receives the URL of every request performed by the returned platform
     */
    private function createPlatform(array &$urls, MockResponse $response): Platform
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$urls, $response): MockResponse {
            $urls[] = $url;

            return $response;
        });

        return Factory::createPlatform('test-key', $httpClient);
    }
}
