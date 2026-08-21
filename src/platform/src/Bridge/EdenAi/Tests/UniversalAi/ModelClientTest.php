<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi\Tests\UniversalAi;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\EdenAi\DocumentParser;
use Symfony\AI\Platform\Bridge\EdenAi\ImageAnalysis;
use Symfony\AI\Platform\Bridge\EdenAi\ImageGeneration;
use Symfony\AI\Platform\Bridge\EdenAi\Ocr;
use Symfony\AI\Platform\Bridge\EdenAi\SpeechToText;
use Symfony\AI\Platform\Bridge\EdenAi\Tts;
use Symfony\AI\Platform\Bridge\EdenAi\UniversalAi\ModelClient;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Bridge\Generic\EmbeddingsModel;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class ModelClientTest extends TestCase
{
    public function testItSupportsSynchronousExpertModels()
    {
        $client = new ModelClient(new MockHttpClient(), 'https://api.edenai.run', 'test-key');

        $this->assertTrue($client->supports(new Ocr('ocr/ocr/google')));
        $this->assertTrue($client->supports(new DocumentParser('ocr/resume_parser/affinda')));
        $this->assertTrue($client->supports(new Tts('audio/tts/amazon/neural')));
        $this->assertTrue($client->supports(new ImageAnalysis('image/object_detection/google')));
        $this->assertTrue($client->supports(new ImageGeneration('image/generation/openai')));
        $this->assertFalse($client->supports(new SpeechToText('audio/speech_to_text_async/openai')));
        $this->assertFalse($client->supports(new CompletionsModel('openai/gpt-4o')));
        $this->assertFalse($client->supports(new EmbeddingsModel('openai/text-embedding-3-small')));
    }

    public function testItSendsStringPayloadAsTextForTextBasedModels()
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            $body = json_decode($options['body'], true);
            self::assertSame(['text' => 'Hello world'], $body['input']);

            return new JsonMockResponse(['status' => 'success', 'output' => []]);
        });

        $client = new ModelClient($httpClient, 'https://api.edenai.run', 'test-key');
        $client->request(new Tts('audio/tts/amazon/neural'), 'Hello world');
        $client->request(new ImageGeneration('image/generation/openai'), 'Hello world');

        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    public function testItUploadsBinaryPayloadBeforeTheRequest()
    {
        $responses = [
            new JsonMockResponse(['file_id' => 'file-42']),
            new JsonMockResponse(['status' => 'success', 'output' => ['text' => '']]),
        ];

        $requests = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests, &$responses) {
            $requests[] = [$method, $url, $options['body'] ?? null];

            return array_shift($responses);
        });

        $client = new ModelClient($httpClient, 'https://api.edenai.run', 'test-key');
        $client->request(new Ocr('ocr/ocr/google'), [
            'file_data' => [
                'data' => base64_encode('binary-image'),
                'filename' => 'scan.jpg',
                'format' => 'image/jpeg',
            ],
        ]);

        $this->assertSame(2, $httpClient->getRequestsCount());
        $this->assertSame('https://api.edenai.run/v3/upload', $requests[0][1]);
        $this->assertSame('https://api.edenai.run/v3/universal-ai', $requests[1][1]);

        $body = json_decode(\is_string($requests[1][2]) ? $requests[1][2] : '', true);
        $this->assertSame('file-42', $body['input']['file']);
        $this->assertArrayNotHasKey('file_data', $body['input']);
    }

    public function testItSendsFileToUniversalAiEndpoint()
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.edenai.run/v3/universal-ai', $url);

            $body = json_decode($options['body'], true);
            self::assertSame('ocr/ocr/google', $body['model']);
            self::assertSame(['file' => 'https://example.com/document.pdf'], $body['input']);

            return new JsonMockResponse(['text' => '']);
        });

        $client = new ModelClient($httpClient, 'https://api.edenai.run', 'test-key');
        $client->request(new Ocr('ocr/ocr/google'), [
            'file' => 'https://example.com/document.pdf',
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItMergesOptionsIntoInput()
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            $body = json_decode($options['body'], true);
            self::assertSame('fr', $body['input']['language']);
            self::assertSame('invoice', $body['input']['document_type']);
            self::assertSame('https://example.com/invoice.pdf', $body['input']['file']);

            return new JsonMockResponse(['extracted_data' => []]);
        });

        $client = new ModelClient($httpClient, 'https://api.edenai.run', 'test-key');
        $client->request(new DocumentParser('ocr/financial_parser/affinda'), [
            'file' => 'https://example.com/invoice.pdf',
        ], [
            'language' => 'fr',
            'document_type' => 'invoice',
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItKeepsRootOptionsAtRootLevel()
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            $body = json_decode($options['body'], true);
            self::assertSame(['ocr/ocr/microsoft'], $body['fallbacks']);
            self::assertTrue($body['show_original_response']);
            self::assertSame(['google' => ['timeout' => 10]], $body['provider_params']);
            self::assertArrayNotHasKey('fallbacks', $body['input']);
            self::assertArrayNotHasKey('show_original_response', $body['input']);
            self::assertArrayNotHasKey('provider_params', $body['input']);

            return new JsonMockResponse(['text' => '']);
        });

        $client = new ModelClient($httpClient, 'https://api.edenai.run', 'test-key');
        $client->request(new Ocr('ocr/ocr/google'), [
            'file' => 'https://example.com/document.pdf',
        ], [
            'fallbacks' => ['ocr/ocr/microsoft'],
            'show_original_response' => true,
            'provider_params' => ['google' => ['timeout' => 10]],
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItAcceptsStringPayloadAsFileReference()
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            $body = json_decode($options['body'], true);
            self::assertSame(['file' => 'a9f3c2e1-6d4b-4f4e-9d2a-1c2b3d4e5f6a'], $body['input']);

            return new JsonMockResponse(['text' => '']);
        });

        $client = new ModelClient($httpClient, 'https://api.edenai.run', 'test-key');
        $client->request(new Ocr('ocr/ocr/google'), 'a9f3c2e1-6d4b-4f4e-9d2a-1c2b3d4e5f6a');

        $this->assertSame(1, $httpClient->getRequestsCount());
    }
}
