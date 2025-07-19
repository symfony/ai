<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\LMStudio\Embeddings;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\LMStudio\Embeddings;
use Symfony\AI\Platform\Bridge\LMStudio\Embeddings\ModelClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(ModelClient::class)]
#[UsesClass(Embeddings::class)]
#[Small]
class ModelClientTest extends TestCase
{
    #[Test]
    public function itIsSupportingTheCorrectModel(): void
    {
        $client = new ModelClient(new MockHttpClient(), 'http://localhost:1234');

        self::assertTrue($client->supports(new Embeddings('test-model')));
    }

    #[Test]
    public function itIsExecutingTheCorrectRequest(): void
    {
        $resultCallback = static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('http://localhost:1234/v1/embeddings', $url);
            self::assertSame('{"model":"test-model","input":"Hello, world!"}', $options['body']);

            return new MockResponse();
        };

        $httpClient = new MockHttpClient([$resultCallback]);
        $client = new ModelClient($httpClient, 'http://localhost:1234');

        $model = new Embeddings('test-model');

        $client->request($model, 'Hello, world!');
    }

    #[Test]
    public function itMergesOptionsWithPayload(): void
    {
        $resultCallback = static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('http://localhost:1234/v1/embeddings', $url);
            self::assertSame(
                '{"custom_option":"value","model":"test-model","input":"Hello, world!"}',
                $options['body']
            );

            return new MockResponse();
        };

        $httpClient = new MockHttpClient([$resultCallback]);
        $client = new ModelClient($httpClient, 'http://localhost:1234');

        $model = new Embeddings('test-model');

        $client->request($model, 'Hello, world!', ['custom_option' => 'value']);
    }

    #[Test]
    public function itHandlesArrayInput(): void
    {
        $resultCallback = static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('http://localhost:1234/v1/embeddings', $url);
            self::assertSame('{"model":"test-model","input":["Hello","world"]}', $options['body']);

            return new MockResponse();
        };

        $httpClient = new MockHttpClient([$resultCallback]);
        $client = new ModelClient($httpClient, 'http://localhost:1234');

        $model = new Embeddings('test-model');

        $client->request($model, ['Hello', 'world']);
    }
}
