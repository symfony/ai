<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\LMStudio\Completions;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\LMStudio\Completions;
use Symfony\AI\Platform\Bridge\LMStudio\Completions\ModelClient;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(ModelClient::class)]
#[UsesClass(Completions::class)]
#[UsesClass(EventSourceHttpClient::class)]
#[Small]
class ModelClientTest extends TestCase
{
    #[Test]
    public function itIsSupportingTheCorrectModel(): void
    {
        $client = new ModelClient(new MockHttpClient(), 'http://localhost:1234');

        self::assertTrue($client->supports(new Completions('test-model')));
    }

    #[Test]
    public function itIsExecutingTheCorrectRequest(): void
    {
        $resultCallback = static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('http://localhost:1234/v1/chat/completions', $url);
            self::assertSame(
                '{"model":"test-model","messages":[{"role":"user","content":"Hello, world!"}]}',
                $options['body']
            );

            return new MockResponse();
        };

        $httpClient = new MockHttpClient([$resultCallback]);
        $client = new ModelClient($httpClient, 'http://localhost:1234');

        $payload = [
            'model' => 'test-model',
            'messages' => [
                ['role' => 'user', 'content' => 'Hello, world!'],
            ],
        ];

        $client->request(new Completions('test-model'), $payload);
    }

    #[Test]
    public function itMergesOptionsWithPayload(): void
    {
        $resultCallback = static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('http://localhost:1234/v1/chat/completions', $url);
            self::assertSame(
                '{"temperature":0.7,"model":"test-model","messages":[{"role":"user","content":"Hello, world!"}]}',
                $options['body']
            );

            return new MockResponse();
        };

        $httpClient = new MockHttpClient([$resultCallback]);
        $client = new ModelClient($httpClient, 'http://localhost:1234');

        $payload = [
            'model' => 'test-model',
            'messages' => [
                ['role' => 'user', 'content' => 'Hello, world!'],
            ],
        ];

        $client->request(new Completions('test-model'), $payload, ['temperature' => 0.7]);
    }

    #[Test]
    public function itUsesEventSourceHttpClient(): void
    {
        $httpClient = new MockHttpClient();
        $client = new ModelClient($httpClient, 'http://localhost:1234');

        $reflection = new \ReflectionProperty($client, 'httpClient');
        $reflection->setAccessible(true);

        self::assertInstanceOf(EventSourceHttpClient::class, $reflection->getValue($client));
    }

    #[Test]
    public function itKeepsExistingEventSourceHttpClient(): void
    {
        $eventSourceHttpClient = new EventSourceHttpClient(new MockHttpClient());
        $client = new ModelClient($eventSourceHttpClient, 'http://localhost:1234');

        $reflection = new \ReflectionProperty($client, 'httpClient');
        $reflection->setAccessible(true);

        self::assertSame($eventSourceHttpClient, $reflection->getValue($client));
    }
}
