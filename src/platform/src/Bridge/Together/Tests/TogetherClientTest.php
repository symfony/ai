<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Together\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Together\Together;
use Symfony\AI\Platform\Bridge\Together\TogetherClient;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class TogetherClientTest extends TestCase
{
    public function testItIsSupportingTheCorrectModel()
    {
        $client = new TogetherClient(new MockHttpClient());

        $this->assertTrue($client->supports(new Together('openai/gpt-oss-120b')));
    }

    public function testItDoesNotSupportOtherModels()
    {
        $client = new TogetherClient(new MockHttpClient());

        $this->assertFalse($client->supports(new Model('gpt-4')));
    }

    public function testItIsExecutingTheCompletionsRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.together.xyz/v1/chat/completions', $url);
            self::assertSame('{"model":"openai\/gpt-oss-120b","messages":[{"role":"user","content":"Hello"}]}', $options['body']);

            return new MockResponse();
        };

        $httpClient = new MockHttpClient($resultCallback, 'https://api.together.xyz');
        $client = new TogetherClient($httpClient);
        $client->request(
            new Together('openai/gpt-oss-120b', [Capability::INPUT_MESSAGES]),
            ['model' => 'openai/gpt-oss-120b', 'messages' => [['role' => 'user', 'content' => 'Hello']]],
        );
    }

    public function testItMergesOptionsIntoTheCompletionsRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('https://api.together.xyz/v1/chat/completions', $url);
            self::assertSame('{"temperature":0.7,"model":"openai\/gpt-oss-120b","messages":[]}', $options['body']);

            return new MockResponse();
        };

        $httpClient = new MockHttpClient($resultCallback, 'https://api.together.xyz');
        $client = new TogetherClient($httpClient);
        $client->request(
            new Together('openai/gpt-oss-120b', [Capability::INPUT_MESSAGES]),
            ['model' => 'openai/gpt-oss-120b', 'messages' => []],
            ['temperature' => 0.7],
        );
    }

    public function testItThrowsOnStringPayloadForCompletions()
    {
        $client = new TogetherClient(new MockHttpClient([], 'https://api.together.xyz'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payload must be an array');

        $client->request(new Together('openai/gpt-oss-120b', [Capability::INPUT_MESSAGES]), 'raw string payload');
    }

    public function testItIsExecutingTheEmbeddingsRequestWithStringInput()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.together.xyz/v1/embeddings', $url);
            self::assertSame('{"model":"BAAI\/bge-large-en-v1.5","input":"Hello World"}', $options['body']);

            return new MockResponse();
        };

        $httpClient = new MockHttpClient($resultCallback, 'https://api.together.xyz');
        $client = new TogetherClient($httpClient);
        $client->request(
            new Together('BAAI/bge-large-en-v1.5', [Capability::INPUT_TEXT, Capability::EMBEDDINGS]),
            'Hello World',
        );
    }

    public function testItIsExecutingTheEmbeddingsRequestWithArrayInput()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('https://api.together.xyz/v1/embeddings', $url);
            self::assertSame('{"model":"BAAI\/bge-large-en-v1.5","input":["First text","Second text"]}', $options['body']);

            return new MockResponse();
        };

        $httpClient = new MockHttpClient($resultCallback, 'https://api.together.xyz');
        $client = new TogetherClient($httpClient);
        $client->request(
            new Together('BAAI/bge-large-en-v1.5', [Capability::INPUT_TEXT, Capability::EMBEDDINGS]),
            ['First text', 'Second text'],
        );
    }

    public function testItIsExecutingTheImageGenerationRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.together.xyz/v1/images/generations', $url);
            self::assertSame('{"response_format":"base64","model":"black-forest-labs\/FLUX.1-schnell","prompt":"a cat"}', $options['body']);

            return new MockResponse();
        };

        $httpClient = new MockHttpClient($resultCallback, 'https://api.together.xyz');
        $client = new TogetherClient($httpClient);
        $client->request(
            new Together('black-forest-labs/FLUX.1-schnell', [Capability::INPUT_TEXT, Capability::OUTPUT_IMAGE, Capability::TEXT_TO_IMAGE]),
            'a cat',
        );
    }

    public function testItIsExecutingTheTextToSpeechRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.together.xyz/v1/audio/speech', $url);
            self::assertSame('{"voice":"friendly","model":"cartesia\/sonic","input":"Hello world"}', $options['body']);

            return new MockResponse();
        };

        $httpClient = new MockHttpClient($resultCallback, 'https://api.together.xyz');
        $client = new TogetherClient($httpClient);
        $client->request(
            new Together('cartesia/sonic', [Capability::TEXT_TO_SPEECH, Capability::OUTPUT_AUDIO]),
            'Hello world',
            ['voice' => 'friendly'],
        );
    }

    public function testItThrowsWhenVoiceIsMissingForTextToSpeech()
    {
        $client = new TogetherClient(new MockHttpClient([], 'https://api.together.xyz'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "voice" option is required');

        $client->request(new Together('cartesia/sonic', [Capability::TEXT_TO_SPEECH, Capability::OUTPUT_AUDIO]), 'Hello');
    }

    public function testItIsExecutingTheSpeechToTextRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.together.xyz/v1/audio/transcriptions', $url);

            return new MockResponse();
        };

        $httpClient = new MockHttpClient($resultCallback, 'https://api.together.xyz');
        $client = new TogetherClient($httpClient);
        $client->request(
            new Together('openai/whisper-large-v3', [Capability::SPEECH_TO_TEXT, Capability::INPUT_AUDIO, Capability::OUTPUT_TEXT]),
            ['input_audio' => ['path' => \dirname(__DIR__, 6).'/fixtures/audio.mp3']],
        );
    }

    public function testItIsExecutingTheSpeechToTextTranslationRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.together.xyz/v1/audio/translations', $url);

            return new MockResponse();
        };

        $httpClient = new MockHttpClient($resultCallback, 'https://api.together.xyz');
        $client = new TogetherClient($httpClient);
        $client->request(
            new Together('openai/whisper-large-v3', [Capability::SPEECH_TO_TEXT, Capability::INPUT_AUDIO, Capability::OUTPUT_TEXT]),
            ['input_audio' => ['path' => \dirname(__DIR__, 6).'/fixtures/audio.mp3']],
            ['task' => 'translation'],
        );
    }

    public function testItThrowsWhenSpeechToTextPayloadHasNoFile()
    {
        $client = new TogetherClient(new MockHttpClient([], 'https://api.together.xyz'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must contain an audio file');

        $client->request(new Together('openai/whisper-large-v3', [Capability::SPEECH_TO_TEXT]), ['foo' => 'bar']);
    }

    public function testItIsExecutingTheRerankRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.together.xyz/v1/rerank', $url);
            self::assertSame('{"model":"Salesforce\/Llama-Rank-v1","query":"What is AI?","documents":["doc a","doc b"]}', $options['body']);

            return new MockResponse();
        };

        $httpClient = new MockHttpClient($resultCallback, 'https://api.together.xyz');
        $client = new TogetherClient($httpClient);
        $client->request(
            new Together('Salesforce/Llama-Rank-v1', [Capability::INPUT_TEXT, Capability::RERANKING]),
            ['query' => 'What is AI?', 'documents' => ['doc a', 'doc b']],
        );
    }

    public function testItAcceptsTextsAsRerankDocumentsAlias()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('https://api.together.xyz/v1/rerank', $url);
            self::assertSame('{"model":"Salesforce\/Llama-Rank-v1","query":"What is AI?","documents":["doc a","doc b"]}', $options['body']);

            return new MockResponse();
        };

        $httpClient = new MockHttpClient($resultCallback, 'https://api.together.xyz');
        $client = new TogetherClient($httpClient);
        $client->request(
            new Together('Salesforce/Llama-Rank-v1', [Capability::INPUT_TEXT, Capability::RERANKING]),
            ['query' => 'What is AI?', 'texts' => ['doc a', 'doc b']],
        );
    }

    public function testItThrowsWhenRerankPayloadHasNoQuery()
    {
        $client = new TogetherClient(new MockHttpClient([], 'https://api.together.xyz'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must contain a "query" key');

        $client->request(new Together('Salesforce/Llama-Rank-v1', [Capability::RERANKING]), ['documents' => ['a']]);
    }

    public function testItThrowsOnModelWithoutSupportedCapability()
    {
        $client = new TogetherClient(new MockHttpClient([], 'https://api.together.xyz'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported model');

        $client->request(new Together('some/unknown-model'), ['prompt' => 'cat']);
    }
}
