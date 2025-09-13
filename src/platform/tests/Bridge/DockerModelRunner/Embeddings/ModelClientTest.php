<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\DockerModelRunner\Embeddings;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\DockerModelRunner\Embeddings;
use Symfony\AI\Platform\Bridge\DockerModelRunner\Embeddings\ModelClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(ModelClient::class)]
#[UsesClass(Embeddings::class)]
#[Small]
class ModelClientTest extends TestCase
{
    public function testItIsSupportingTheCorrectModel()
    {
        $client = new ModelClient(new MockHttpClient(), 'http://localhost:1234');

        $this->assertTrue($client->supports(new Embeddings('test-model')));
    }

    public function testItIsExecutingTheCorrectRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('http://localhost:1234/engines/v1/embeddings', $url);
            self::assertSame('{"model":"test-model","input":"Hello, world!"}', $options['body']);

            return new MockResponse();
        };

        $httpClient = new MockHttpClient([$resultCallback]);
        $client = new ModelClient($httpClient, 'http://localhost:1234');

        $model = new Embeddings('test-model');

        $client->request($model, 'Hello, world!');
    }

    public function testItMergesOptionsWithPayload()
    {
        $resultCallback = static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('http://localhost:1234/engines/v1/embeddings', $url);
            self::assertSame('{"custom_option":"value","model":"test-model","input":"Hello, world!"}', $options['body']);

            return new MockResponse();
        };

        $httpClient = new MockHttpClient([$resultCallback]);
        $client = new ModelClient($httpClient, 'http://localhost:1234');

        $model = new Embeddings('test-model');

        $client->request($model, 'Hello, world!', ['custom_option' => 'value']);
    }

    public function testItHandlesArrayInput()
    {
        $resultCallback = static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('http://localhost:1234/engines/v1/embeddings', $url);
            self::assertSame('{"model":"test-model","input":["Hello","world"]}', $options['body']);

            return new MockResponse();
        };

        $httpClient = new MockHttpClient([$resultCallback]);
        $client = new ModelClient($httpClient, 'http://localhost:1234');

        $model = new Embeddings('test-model');

        $client->request($model, ['Hello', 'world']);
    }
}
