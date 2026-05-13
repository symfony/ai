<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bifrost\Tests\Image;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Bifrost\Image\ImageModel;
use Symfony\AI\Platform\Bridge\Bifrost\Image\ImageModelClient;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\ScopingHttpClient;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ImageModelClientTest extends TestCase
{
    public function testItSupportsImageModelOnly()
    {
        $client = new ImageModelClient(new MockHttpClient());

        $this->assertTrue($client->supports(new ImageModel('openai/dall-e-3')));
        $this->assertFalse($client->supports(new Model('test-model')));
    }

    public function testItSendsExpectedRequest()
    {
        $mock = new MockHttpClient([
            static function (string $method, string $url, array $options): MockResponse {
                self::assertSame('POST', $method);
                self::assertSame('http://localhost:8080/v1/images/generations', $url);

                $headers = $options['normalized_headers'] ?? [];
                self::assertIsArray($headers);
                self::assertArrayHasKey('authorization', $headers);
                self::assertIsArray($headers['authorization']);
                self::assertSame('Authorization: Bearer sk-bf-test', $headers['authorization'][0]);

                $rawBody = $options['body'] ?? null;
                self::assertIsString($rawBody);
                $body = json_decode($rawBody, true);
                self::assertIsArray($body);
                self::assertSame('openai/dall-e-3', $body['model']);
                self::assertSame('A friendly red panda', $body['prompt']);
                self::assertSame('1024x1024', $body['size']);

                return new MockResponse('{"data":[{"url":"https://example.com/image.png"}]}', ['http_code' => 200]);
            },
        ]);

        $client = new ImageModelClient(ScopingHttpClient::forBaseUri($mock, 'http://localhost:8080', [
            'auth_bearer' => 'sk-bf-test',
        ]));
        $client->request(new ImageModel('openai/dall-e-3'), 'A friendly red panda', ['size' => '1024x1024']);

        $this->assertSame(1, $mock->getRequestsCount());
    }

    public function testItAcceptsArrayPayloadWithPromptKey()
    {
        $mock = new MockHttpClient([
            static function (string $method, string $url, array $options): MockResponse {
                $rawBody = $options['body'] ?? null;
                self::assertIsString($rawBody);
                $body = json_decode($rawBody, true);
                self::assertIsArray($body);
                self::assertSame('A photo of a cat', $body['prompt']);

                return new MockResponse('{"data":[{"url":"https://example.com/image.png"}]}', ['http_code' => 200]);
            },
        ]);

        $client = new ImageModelClient(ScopingHttpClient::forBaseUri($mock, 'http://localhost:8080'));
        $client->request(new ImageModel('openai/dall-e-3'), ['prompt' => 'A photo of a cat']);

        $this->assertSame(1, $mock->getRequestsCount());
    }

    public function testItFailsWhenPayloadShapeIsInvalid()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The payload must be a string or contain a string "prompt" key.');

        $client = new ImageModelClient(new MockHttpClient());
        $client->request(new ImageModel('openai/dall-e-3'), ['foo' => 'bar']);
    }
}
