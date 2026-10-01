<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic\Tests\Batch;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\Batch\BatchClient;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class BatchClientTest extends TestCase
{
    public function testItSubmitsTheRequestsInlineAndCreatesTheBatch()
    {
        $recorded = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$recorded): MockResponse {
            $recorded = [$method, $url, $options];

            return new MockResponse('{"id": "msgbatch_123", "processing_status": "in_progress"}');
        });

        $result = (new BatchClient($httpClient, 'sk-ant-test', 'https://api.anthropic.com'))->submit([
            'capital-fr' => ['model' => 'claude-3-5-sonnet-latest', 'max_tokens' => 50, 'messages' => [['role' => 'user', 'content' => 'What is the capital of France?']]],
            'capital-de' => ['model' => 'claude-3-5-sonnet-latest', 'max_tokens' => 50, 'messages' => [['role' => 'user', 'content' => 'What is the capital of Germany?']]],
        ]);

        [$method, $url, $options] = $recorded;

        $this->assertSame('POST', $method);
        // Anthropic takes the requests inline, so there is no file to upload first.
        $this->assertSame('https://api.anthropic.com/v1/messages/batches', $url);
        $this->assertSame(['id' => 'msgbatch_123', 'processing_status' => 'in_progress'], $result->getData());

        $body = json_decode($options['body'], true);

        $this->assertSame([
            [
                'custom_id' => 'capital-fr',
                'params' => ['model' => 'claude-3-5-sonnet-latest', 'max_tokens' => 50, 'messages' => [['role' => 'user', 'content' => 'What is the capital of France?']]],
            ],
            [
                'custom_id' => 'capital-de',
                'params' => ['model' => 'claude-3-5-sonnet-latest', 'max_tokens' => 50, 'messages' => [['role' => 'user', 'content' => 'What is the capital of Germany?']]],
            ],
        ], $body['requests']);

        $headers = self::headers($options);

        $this->assertSame('sk-ant-test', $headers['x-api-key']);
        $this->assertSame('2023-06-01', $headers['anthropic-version']);
        $this->assertArrayNotHasKey('anthropic-beta', $headers);
    }

    public function testItHeadsTheBetaFeaturesTheRequestsNeed()
    {
        $recorded = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$recorded): MockResponse {
            $recorded = $options;

            return new MockResponse('{"id": "msgbatch_123"}');
        });

        (new BatchClient($httpClient, 'sk-ant-test', 'https://api.anthropic.com'))->submit(
            ['first' => ['model' => 'claude-3-5-sonnet-latest']],
            ['interleaved-thinking-2025-05-14', 'another-beta'],
        );

        $this->assertSame('interleaved-thinking-2025-05-14,another-beta', self::headers($recorded)['anthropic-beta']);
    }

    public function testItSubmitsToTheConfiguredBaseUrl()
    {
        $urls = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$urls): MockResponse {
            $urls[] = $url;

            return new MockResponse('{"id": "msgbatch_123"}');
        });

        (new BatchClient($httpClient, 'sk-ant-test', 'https://gateway.example.com'))->submit([
            'first' => ['model' => 'claude-3-5-sonnet-latest'],
        ]);

        $this->assertSame(['https://gateway.example.com/v1/messages/batches'], $urls);
    }

    public function testItRejectsAnEmptyBatch()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A batch needs at least one request.');

        (new BatchClient(new MockHttpClient(), 'sk-ant-test', 'https://api.anthropic.com'))->submit([]);
    }

    /**
     * Anthropic constrains the identifier to `^[a-zA-Z0-9_-]{1,64}$` and rejects the whole batch
     * over a single one it does not accept.
     */
    #[TestWith(['capital fr'])]
    #[TestWith(['capital.fr'])]
    #[TestWith(['capital/fr'])]
    #[TestWith([''])]
    public function testItRejectsAnIdentifierAnthropicWouldNotAccept(string $customId)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The identifier of a batch request must be 1 to 64 characters long and contain only letters, digits, hyphens and underscores');

        (new BatchClient(new MockHttpClient(), 'sk-ant-test', 'https://api.anthropic.com'))->submit([
            $customId => ['model' => 'claude-3-5-sonnet-latest'],
        ]);
    }

    public function testItRejectsAnIdentifierThatIsTooLong()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The identifier of a batch request must be 1 to 64 characters long');

        (new BatchClient(new MockHttpClient(), 'sk-ant-test', 'https://api.anthropic.com'))->submit([
            str_repeat('a', 65) => ['model' => 'claude-3-5-sonnet-latest'],
        ]);
    }

    public function testItAcceptsTheIdentifiersAnthropicDocuments()
    {
        $httpClient = new MockHttpClient(new MockResponse('{"id": "msgbatch_123"}'));

        $result = (new BatchClient($httpClient, 'sk-ant-test', 'https://api.anthropic.com'))->submit([
            'my-first-request_1' => ['model' => 'claude-3-5-sonnet-latest'],
            str_repeat('a', 64) => ['model' => 'claude-3-5-sonnet-latest'],
        ]);

        $this->assertSame('msgbatch_123', $result->getData()['id']);
    }

    /**
     * An integer key survives the normalization as an integer, and is still a valid identifier.
     */
    public function testItAcceptsANumericIdentifier()
    {
        $recorded = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$recorded): MockResponse {
            $recorded = $options;

            return new MockResponse('{"id": "msgbatch_123"}');
        });

        (new BatchClient($httpClient, 'sk-ant-test', 'https://api.anthropic.com'))->submit([
            42 => ['model' => 'claude-3-5-sonnet-latest'],
        ]);

        $this->assertSame('42', json_decode($recorded['body'], true)['requests'][0]['custom_id']);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, string>
     */
    private static function headers(array $options): array
    {
        $headers = [];

        foreach ($options['headers'] as $key => $value) {
            if (\is_int($key)) {
                [$key, $value] = explode(': ', $value, 2);
            }

            $headers[strtolower($key)] = $value;
        }

        return $headers;
    }
}
