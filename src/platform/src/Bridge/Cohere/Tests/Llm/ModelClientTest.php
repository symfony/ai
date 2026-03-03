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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Cohere\Cohere;
use Symfony\AI\Platform\Bridge\Cohere\Embeddings;
use Symfony\AI\Platform\Bridge\Cohere\Llm\ModelClient;
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
        $resultCallback = static function (
            string $method,
            string $url,
            array $options,
        ): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.cohere.com/v2/chat', $url);
            self::assertStringContainsString('Bearer test-key', $options['normalized_headers']['authorization'][0]);

            return new MockResponse();
        };

        $httpClient = new MockHttpClient([$resultCallback]);
        $client = new ModelClient($httpClient, 'test-key');

        $client->request(new Cohere('command-a-03-2025'), ['model' => 'command-a-03-2025', 'messages' => []]);
    }
}
