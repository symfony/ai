<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Replicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Replicate\Client;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
#[CoversClass(Client::class)]
final class ClientTest extends TestCase
{
    public function testRequestWithImmediateSuccess()
    {
        $httpClient = new MockHttpClient(new MockResponse('{"status": "succeeded", "output": ["Hello"]}'));

        $client = new Client($httpClient, new MockClock(), 'test-api-key');
        $response = $client->request('meta/llama-3.1-405b-instruct', 'predictions', ['prompt' => 'Hello']);

        $data = $response->toArray();
        $this->assertSame('succeeded', $data['status']);
        $this->assertSame(['Hello'], $data['output']);
    }

    public function testRequestWithPolling()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"id": "pred-123", "status": "starting"}'),
            new MockResponse('{"id": "pred-123", "status": "processing"}'),
            new MockResponse('{"id": "pred-123", "status": "succeeded", "output": ["World"]}'),
        ]);

        $client = new Client($httpClient, new MockClock(), 'test-api-key');
        $response = $client->request('meta/llama-3.1-405b-instruct', 'predictions', ['prompt' => 'Hello']);

        $data = $response->toArray();
        $this->assertSame('succeeded', $data['status']);
        $this->assertSame(['World'], $data['output']);
    }

    public function testRequestAuthenticationHeader()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            self::assertSame('Authorization: Bearer secret-key', $options['normalized_headers']['authorization'][0]);

            return new MockResponse('{"status": "succeeded"}');
        });

        $client = new Client($httpClient, new MockClock(), 'secret-key');
        $client->request('meta/llama-3.1-405b-instruct', 'predictions', ['prompt' => 'test']);
    }

    public function testRequestUrl()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) {
            self::assertSame('POST', $method);
            self::assertSame('https://api.replicate.com/v1/models/meta/llama-3.1-405b-instruct/predictions', $url);

            return new MockResponse('{"status": "succeeded"}');
        });

        $client = new Client($httpClient, new MockClock(), 'test-key');
        $client->request('meta/llama-3.1-405b-instruct', 'predictions', ['prompt' => 'test']);
    }
}
