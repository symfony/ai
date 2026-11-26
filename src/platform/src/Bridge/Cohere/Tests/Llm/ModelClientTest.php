<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cohere\Tests\Llm;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Cohere\Cohere;
use Symfony\AI\Platform\Bridge\Cohere\Embeddings;
use Symfony\AI\Platform\Bridge\Cohere\Llm\ModelClient;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ModelClientTest extends TestCase
{
    public function testItSupportsCohereModel()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->assertTrue($client->supports(new Cohere('command-a-03-2025')));
    }

    public function testItDoesNotSupportEmbeddingsModel()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->assertFalse($client->supports(new Embeddings('embed-english-v3.0')));
    }

    public function testItSendsExpectedRequest()
    {
        $httpClient = new MockHttpClient([function (
            string $method,
            string $url,
            array $options,
        ): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.cohere.com/v2/chat', $url);
            $this->assertStringContainsString('Bearer test-key', $options['normalized_headers']['authorization'][0]);

            return new MockResponse();
        }]);

        $client = new ModelClient($httpClient, 'test-key');

        $client->request(new Cohere('command-a-03-2025'), ['model' => 'command-a-03-2025', 'messages' => []]);
    }

    public function testCustomBaseUrlIsUsedAndTrailingSlashNormalized()
    {
        $httpClient = new MockHttpClient([function (string $method, string $url): MockResponse {
            $this->assertSame('https://x.example.com/v2/chat', $url);

            return new MockResponse();
        }]);

        $client = new ModelClient($httpClient, 'test-key', 'https://x.example.com/');
        $client->request(new Cohere('command-a-03-2025'), ['model' => 'command-a-03-2025', 'messages' => []]);
    }

    public function testStringPayloadThrowsException()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payload must be an array, but a string was given');

        $client->request(new Cohere('command-a-03-2025'), 'string payload');
    }

    public function testMalformedUtf8InPayloadDoesNotAbortTheRequest()
    {
        $httpClient = new MockHttpClient([function (
            string $method,
            string $url,
            array $options,
        ): MockResponse {
            $this->assertSame('Content-Type: application/json', $options['normalized_headers']['content-type'][0]);
            $this->assertJson($options['body']);
            $this->assertStringContainsString('tool output \ufffd here', $options['body']);

            return new MockResponse();
        }]);

        $client = new ModelClient($httpClient, 'test-key');

        $client->request(new Cohere('command-a-03-2025'), ['model' => 'command-a-03-2025', 'messages' => [['role' => 'user', 'content' => "tool output \xB1 here"]]]);
    }

    /**
     * @param array<string, int> $options
     */
    #[DataProvider('provideTokenLimits')]
    public function testOutputTokenOptions(array $options, ?int $expected)
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $httpOptions) use ($expected): MockResponse {
            $body = json_decode($httpOptions['body'], true, flags: \JSON_THROW_ON_ERROR);

            $this->assertSame($expected, $body['max_tokens'] ?? null);
            $this->assertArrayNotHasKey(PlatformOptions::MAX_OUTPUT_TOKENS, $body);
            $this->assertSame('command-a-03-2025', $body['model']);
            $this->assertSame([['role' => 'user', 'content' => 'Hello']], $body['messages']);

            return new MockResponse();
        });
        $client = new ModelClient($httpClient, 'test-key');

        $client->request(new Cohere('command-a-03-2025'), [
            'model' => 'command-a-03-2025',
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ], $options);
    }

    /**
     * @return iterable<string, array{array<string, int>, int|null}>
     */
    public static function provideTokenLimits(): iterable
    {
        yield 'canonical limit' => [[PlatformOptions::MAX_OUTPUT_TOKENS => 500], 500];
        yield 'provider limit' => [['max_tokens' => 2000], 2000];
        yield 'provider limit wins' => [[PlatformOptions::MAX_OUTPUT_TOKENS => 500, 'max_tokens' => 2000], 2000];
        yield 'zero provider limit is preserved' => [[PlatformOptions::MAX_OUTPUT_TOKENS => 500, 'max_tokens' => 0], 0];
        yield 'zero canonical limit is forwarded' => [[PlatformOptions::MAX_OUTPUT_TOKENS => 0], 0];
        yield 'no token limit' => [[], null];
    }
}
