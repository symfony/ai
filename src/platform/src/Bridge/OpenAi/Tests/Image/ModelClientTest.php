<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Tests\Image;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Image;
use Symfony\AI\Platform\Bridge\OpenAi\Image\ModelClient;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\Image as ImageContent;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

final class ModelClientTest extends TestCase
{
    public function testItThrowsExceptionWhenApiKeyIsEmpty()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The API key must not be empty.');

        new ModelClient(new MockHttpClient(), '');
    }

    #[TestWith(['api-key-without-prefix'])]
    #[TestWith(['pk-api-key'])]
    #[TestWith(['SK-api-key'])]
    #[TestWith(['skapikey'])]
    #[TestWith(['sk api-key'])]
    #[TestWith(['sk'])]
    public function testItThrowsExceptionWhenApiKeyDoesNotStartWithSk(string $invalidApiKey)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The API key must start with "sk-".');

        new ModelClient(new MockHttpClient(), $invalidApiKey);
    }

    public function testItAcceptsValidApiKey()
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'sk-valid-api-key');

        $this->assertInstanceOf(ModelClient::class, $modelClient);
    }

    public function testItIsSupportingTheCorrectModel()
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'sk-api-key');

        $this->assertTrue($modelClient->supports(new Image('gpt-image-1')));
    }

    public function testItIsExecutingTheCorrectRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.openai.com/v1/images/generations', $url);
            self::assertSame('Authorization: Bearer sk-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"n":1,"model":"gpt-image-1","prompt":"foo"}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'sk-api-key');
        $modelClient->request(new Image('gpt-image-1'), 'foo', ['n' => 1]);
    }

    public function testItEditsAnImageUsingTheEditsEndpoint()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.openai.com/v1/images/edits', $url);
            self::assertSame('Authorization: Bearer sk-api-key', $options['normalized_headers']['authorization'][0]);

            $contentType = $options['normalized_headers']['content-type'][0];
            self::assertStringStartsWith('Content-Type: multipart/form-data; boundary=', $contentType);

            // Depending on the HttpClient version the normalized body is a string, a generator, or a
            // Closure(int): string streaming chunks; materialize all three into a single string.
            $rawBody = $options['body'];
            if (\is_string($rawBody)) {
                $body = $rawBody;
            } elseif ($rawBody instanceof \Closure) {
                $body = '';
                while ('' !== ($chunk = $rawBody(8192))) {
                    $body .= $chunk;
                }
            } else {
                $body = '';
                foreach ($rawBody as $chunk) {
                    $body .= $chunk;
                }
            }
            self::assertStringContainsString('name="model"', $body);
            self::assertStringContainsString('gpt-image-1', $body);
            self::assertStringContainsString('name="prompt"', $body);
            self::assertStringContainsString('make it red', $body);
            self::assertStringContainsString('name="image"; filename="image.jpg"', $body);
            self::assertStringContainsString('Content-Type: image/jpeg', $body);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'sk-api-key');
        $modelClient->request(new Image('gpt-image-1'), 'make it red', [
            'image' => ImageContent::fromFile(\dirname(__DIR__, 7).'/fixtures/image.jpg'),
        ]);
    }

    public function testItThrowsWhenPromptIsNotAString()
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'sk-api-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The image prompt must be a string');

        $modelClient->request(new Image('gpt-image-1'), ['not', 'a', 'string']);
    }

    #[TestWith(['EU', 'https://eu.api.openai.com/v1/images/generations'])]
    #[TestWith(['US', 'https://us.api.openai.com/v1/images/generations'])]
    #[TestWith([null, 'https://api.openai.com/v1/images/generations'])]
    public function testItUsesCorrectBaseUrl(?string $region, string $expectedUrl)
    {
        $resultCallback = static function (string $method, string $url, array $options) use ($expectedUrl): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame($expectedUrl, $url);
            self::assertSame('Authorization: Bearer sk-api-key', $options['normalized_headers']['authorization'][0]);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'sk-api-key', $region);
        $modelClient->request(new Image('gpt-image-1'), 'foo', ['n' => 1]);
    }

    public function testHttpOptionsAreSentWithTheRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame(30.0, $options['max_duration']);
            self::assertSame('{"model":"gpt-image-1","prompt":"foo"}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'sk-api-key');
        $modelClient->request(new Image('gpt-image-1'), 'foo', ['http_options' => ['max_duration' => 30, 'json' => 'overridden']]);
    }
}
