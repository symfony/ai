<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Tests\Gemini;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Bridge\Gemini\Gemini\ModelClient;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ModelClientTest extends TestCase
{
    public function testItSupportsGeminiModel()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->assertTrue($client->supports(new Gemini('gemini-2.0-flash')));
    }

    public function testItDoesNotSupportOtherModels()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->assertFalse($client->supports(new Model('any-model')));
    }

    public function testItSendsExpectedRequest()
    {
        $payload = [
            'contents' => [
                ['parts' => [['text' => 'Hello, world!']]],
            ],
        ];
        $expectedResponse = [
            'candidates' => [$payload],
        ];

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use ($expectedResponse): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent', $url);
            $this->assertContains('x-goog-api-key: test-key', $options['headers']);

            return new MockResponse(json_encode($expectedResponse));
        });

        $client = new ModelClient($httpClient, 'test-key');
        $result = $client->request(new Gemini('gemini-2.0-flash'), $payload);

        $this->assertSame($expectedResponse, $result->getData());
    }

    public function testItSendsStreamRequest()
    {
        $httpClient = new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertStringContainsString('streamGenerateContent', $url);

            return new MockResponse('{}');
        });

        $client = new ModelClient($httpClient, 'test-key');
        $client->request(new Gemini('gemini-2.0-flash'), [
            'contents' => [['parts' => [['text' => 'Hello']]]],
        ], ['stream' => true]);
    }

    public function testStringPayloadThrowsException()
    {
        $client = new ModelClient(new MockHttpClient(), 'test-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payload must be an array, but a string was given');

        $client->request(new Gemini('gemini-2.0-flash'), 'string payload');
    }
}
