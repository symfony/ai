<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cohere\Tests\Reranker;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Cohere\Cohere;
use Symfony\AI\Platform\Bridge\Cohere\Reranker;
use Symfony\AI\Platform\Bridge\Cohere\Reranker\ModelClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ModelClientTest extends TestCase
{
    public function testItSupportsRerankerModel()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->assertTrue($client->supports(new Reranker('rerank-v3.5')));
    }

    public function testItDoesNotSupportCohereModel()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->assertFalse($client->supports(new Cohere('command-a-03-2025')));
    }

    public function testItSendsExpectedRequest()
    {
        $resultCallback = static function (
            string $method,
            string $url,
            array $options,
        ): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.cohere.com/v2/rerank', $url);

            $body = json_decode($options['body'], true);
            self::assertSame('rerank-v3.5', $body['model']);
            self::assertSame('What is AI?', $body['query']);
            self::assertSame(['Document about AI', 'Document about cooking'], $body['documents']);
            self::assertArrayNotHasKey('top_n', $body);

            return new MockResponse();
        };

        $httpClient = new MockHttpClient([$resultCallback]);
        $client = new ModelClient($httpClient, 'test-key');

        $client->request(new Reranker('rerank-v3.5'), [
            'query' => 'What is AI?',
            'texts' => ['Document about AI', 'Document about cooking'],
        ]);
    }

    public function testItSendsTopNOption()
    {
        $resultCallback = static function (
            string $method,
            string $url,
            array $options,
        ): MockResponse {
            $body = json_decode($options['body'], true);
            self::assertSame(3, $body['top_n']);

            return new MockResponse();
        };

        $httpClient = new MockHttpClient([$resultCallback]);
        $client = new ModelClient($httpClient, 'test-key');

        $client->request(new Reranker('rerank-v3.5'), [
            'query' => 'What is AI?',
            'texts' => ['Doc 1', 'Doc 2', 'Doc 3', 'Doc 4'],
        ], ['top_n' => 3]);
    }
}
