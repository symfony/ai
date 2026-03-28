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
use Symfony\AI\Platform\Exception\InvalidArgumentException;
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

    public function testStringPayloadThrowsException()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payload must be an array, but a string was given');

        $client->request(new Cohere('command-a-03-2025'), 'string payload');
    }
}
