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

            $body = self::materializeBody($options['body']);
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

    public function testItEditsSeveralImagesAsAnArrayField()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('https://api.openai.com/v1/images/edits', $url);

            $body = self::materializeBody($options['body']);
            self::assertSame(2, substr_count($body, 'name="image[]"'));
            self::assertSame(2, substr_count($body, "Content-Type: image/jpeg\r\n"));
            self::assertStringNotContainsString('name="image";', $body);
            self::assertStringContainsString('make a poster', $body);

            $firstImage = strpos($body, 'name="image[]"; filename="image.jpg"');
            $secondImage = strpos($body, 'name="image[]"; filename="accordion.jpg"');
            self::assertIsInt($firstImage);
            self::assertIsInt($secondImage);
            self::assertLessThan($secondImage, $firstImage);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'sk-api-key');
        $fixtures = \dirname(__DIR__, 7).'/fixtures';
        $modelClient->request(new Image('gpt-image-1'), 'make a poster', [
            'image' => [
                ImageContent::fromFile($fixtures.'/image.jpg'),
                ImageContent::fromFile($fixtures.'/accordion.jpg'),
            ],
        ]);
    }

    public function testItEditsASingleImageListAsAPlainField()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('https://api.openai.com/v1/images/edits', $url);

            $body = self::materializeBody($options['body']);
            self::assertStringContainsString('name="image"; filename="image.jpg"', $body);
            self::assertStringNotContainsString('name="image[]"', $body);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'sk-api-key');
        $modelClient->request(new Image('gpt-image-1'), 'make it red', [
            'image' => [ImageContent::fromFile(\dirname(__DIR__, 7).'/fixtures/image.jpg')],
        ]);
    }

    #[TestWith(['/path/to/image.png', 'string'])]
    #[TestWith([[], 'array'])]
    #[TestWith([['first' => 'not-a-list'], 'array'])]
    #[TestWith([['not-an-image'], 'string'])]
    public function testItThrowsWhenTheImageOptionIsInvalid(mixed $image, string $givenType)
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'sk-api-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The "image" option must be an instance of "%s" or a non-empty list of them, but "%s" was given.', ImageContent::class, $givenType));

        $modelClient->request(new Image('gpt-image-1'), 'make a poster', ['image' => $image]);
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

    /**
     * @param string|\Closure(int): string|iterable<string> $rawBody
     */
    private static function materializeBody(string|\Closure|iterable $rawBody): string
    {
        // The normalized body is a string, a generator or a chunk-streaming Closure, depending on the HttpClient version.
        if (\is_string($rawBody)) {
            return $rawBody;
        }

        $body = '';
        if ($rawBody instanceof \Closure) {
            while ('' !== ($chunk = $rawBody(8192))) {
                $body .= $chunk;
            }

            return $body;
        }

        foreach ($rawBody as $chunk) {
            $body .= $chunk;
        }

        return $body;
    }
}
