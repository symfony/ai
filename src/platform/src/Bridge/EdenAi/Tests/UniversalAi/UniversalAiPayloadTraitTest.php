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
use Symfony\AI\Platform\Bridge\EdenAi\ImageGeneration;
use Symfony\AI\Platform\Bridge\EdenAi\Ocr;
use Symfony\AI\Platform\Bridge\EdenAi\Tts;
use Symfony\AI\Platform\Bridge\EdenAi\UniversalAi\ModelClient;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Covers the request-body assembly shared by the synchronous and asynchronous clients.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class UniversalAiPayloadTraitTest extends TestCase
{
    public function testPayloadEntriesWinOverOptionsOfTheSameName()
    {
        $body = $this->capture(
            new Ocr('ocr/ocr/google'),
            ['file' => 'https://example.com/from-payload.pdf'],
            ['file' => 'https://example.com/from-options.pdf', 'language' => 'en'],
        );

        $this->assertSame('https://example.com/from-payload.pdf', $body['input']['file']);
        $this->assertSame('en', $body['input']['language']);
    }

    public function testItSendsTheModelNameAtTheRoot()
    {
        $body = $this->capture(new Ocr('ocr/ocr/microsoft'), 'a9f3c2e1-6d4b-4f4e-9d2a-1c2b3d4e5f6a');

        $this->assertSame('ocr/ocr/microsoft', $body['model']);
    }

    public function testItMapsAStringPayloadToTextForTextDrivenModels()
    {
        $this->assertSame(['text' => 'Hello'], $this->capture(new Tts('audio/tts/openai/tts-1'), 'Hello')['input']);
        $this->assertSame(['text' => 'Hello'], $this->capture(new ImageGeneration('image/generation/openai'), 'Hello')['input']);
    }

    public function testItMapsAStringPayloadToFileForFileDrivenModels()
    {
        $this->assertSame(['file' => 'https://example.com/a.pdf'], $this->capture(new Ocr('ocr/ocr/google'), 'https://example.com/a.pdf')['input']);
    }

    /**
     * An unusable "file_data" entry must not reach the billable endpoint as an empty upload.
     */
    public function testItRejectsBinaryPayloadsWithoutData()
    {
        $httpClient = new MockHttpClient(new MockResponse('', ['http_code' => 500]));
        $client = new ModelClient($httpClient, 'https://api.edenai.run', 'test-key');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Eden AI binary input does not contain any data.');

        $client->request(new Ocr('ocr/ocr/google'), ['file_data' => ['filename' => 'scan.jpg', 'format' => 'image/jpeg']]);

        $this->assertSame(0, $httpClient->getRequestsCount());
    }

    public function testItRejectsBinaryPayloadsThatAreNotDecodable()
    {
        $httpClient = new MockHttpClient(new MockResponse('', ['http_code' => 500]));
        $client = new ModelClient($httpClient, 'https://api.edenai.run', 'test-key');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Eden AI binary input is not valid base64 content.');

        $client->request(new Ocr('ocr/ocr/google'), ['file_data' => ['data' => '!!! not base64 !!!']]);
    }

    /**
     * A "file_data" entry that is not an array is a caller mistake, not a file reference,
     * and must not be forwarded verbatim into the input object.
     */
    public function testItRejectsANonArrayBinaryPayload()
    {
        $httpClient = new MockHttpClient(new MockResponse('', ['http_code' => 500]));
        $client = new ModelClient($httpClient, 'https://api.edenai.run', 'test-key');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Eden AI binary input must be an array.');

        $client->request(new Ocr('ocr/ocr/google'), ['file_data' => 'not-an-array']);
    }

    public function testItDefaultsTheMimeTypeOfABinaryPayload()
    {
        $uploadHeaders = null;

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$uploadHeaders) {
            if (str_ends_with($url, '/v3/upload')) {
                $uploadHeaders = $options['normalized_headers'];

                return new JsonMockResponse(['file_id' => 'file-42']);
            }

            return new JsonMockResponse(['status' => 'success', 'output' => ['text' => '']]);
        });

        $client = new ModelClient($httpClient, 'https://api.edenai.run', 'test-key');
        $client->request(new Ocr('ocr/ocr/google'), ['file_data' => ['data' => base64_encode('bytes')]]);

        $this->assertNotNull($uploadHeaders);
        $this->assertSame(2, $httpClient->getRequestsCount());
    }

    /**
     * @param array<string, mixed>|string $payload
     * @param array<string, mixed>        $options
     *
     * @return array<string, mixed>
     */
    private function capture(object $model, array|string $payload, array $options = []): array
    {
        $captured = [];

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $httpOptions) use (&$captured): JsonMockResponse {
            $captured = json_decode((string) $httpOptions['body'], true);

            return new JsonMockResponse(['status' => 'success', 'output' => ['text' => '']]);
        });

        $client = new ModelClient($httpClient, 'https://api.edenai.run', 'test-key');
        $client->request($model, $payload, $options);

        return $captured;
    }
}
