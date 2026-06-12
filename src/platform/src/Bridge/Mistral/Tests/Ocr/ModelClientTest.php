<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Tests\Ocr;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Mistral\Embeddings;
use Symfony\AI\Platform\Bridge\Mistral\Mistral;
use Symfony\AI\Platform\Bridge\Mistral\Ocr;
use Symfony\AI\Platform\Bridge\Mistral\Ocr\ModelClient;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ModelClientTest extends TestCase
{
    public function testItSupportsOcrModelOnly()
    {
        $client = new ModelClient(new MockHttpClient(), 'sk-test-key');

        $this->assertTrue($client->supports(new Ocr('mistral-ocr-latest')));
        $this->assertFalse($client->supports(new Mistral('mistral-large-latest')));
        $this->assertFalse($client->supports(new Embeddings('mistral-embed')));
    }

    public function testItSendsDocumentToOcrEndpoint()
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.mistral.ai/v1/ocr', $url);

            $body = json_decode($options['body'], true);
            self::assertSame('mistral-ocr-latest', $body['model']);
            self::assertSame([
                'type' => 'document_url',
                'document_url' => 'https://example.com/document.pdf',
            ], $body['document']);

            return new JsonMockResponse(['pages' => []]);
        });

        $client = new ModelClient($httpClient, 'sk-test-key');
        $client->request(new Ocr('mistral-ocr-latest'), [
            'type' => 'document_url',
            'document_url' => 'https://example.com/document.pdf',
        ]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItForwardsOptions()
    {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            $body = json_decode($options['body'], true);
            self::assertTrue($body['include_image_base64']);

            return new JsonMockResponse(['pages' => []]);
        });

        $client = new ModelClient($httpClient, 'sk-test-key');
        $client->request(new Ocr('mistral-ocr-latest'), [
            'type' => 'image_url',
            'image_url' => 'https://example.com/image.png',
        ], ['include_image_base64' => true]);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testItThrowsExceptionForStringPayload()
    {
        $client = new ModelClient(new MockHttpClient(), 'sk-test-key');

        $this->expectException(InvalidArgumentException::class);

        $client->request(new Ocr('mistral-ocr-latest'), 'not-an-array');
    }
}
