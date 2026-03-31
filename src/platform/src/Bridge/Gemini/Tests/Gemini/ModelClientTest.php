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
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class ModelClientTest extends TestCase
{
    public function testItInvokesTheTextModelsSuccessfully()
    {
        $payload = [
            'contents' => [
                ['parts' => [['text' => 'Hello, world!']]],
            ],
        ];
        $expectedResponse = [
            'candidates' => [$payload],
        ];
        $httpClient = new MockHttpClient(
            new JsonMockResponse($expectedResponse),
        );

        $client = new ModelClient($httpClient, 'test-api-key');

        $result = $client->request(new Gemini('gemini-2.0-flash'), $payload);
        $data = $result->getData();
        $info = $result->getObject()->getInfo();

        $this->assertNotEmpty($data);
        $this->assertNotEmpty($info);
        $this->assertSame('POST', $info['http_method']);
        $this->assertSame(
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent',
            $info['url'],
        );
        $this->assertSame($expectedResponse, $data);
    }

    public function testItThrowsExceptionWhenCombiningToolsWithJsonResponseFormat()
    {
        $httpClient = new MockHttpClient();
        $client = new ModelClient($httpClient, 'test-api-key');

        $payload = [
            'contents' => [
                ['parts' => [['text' => 'Hello']]],
            ],
        ];

        $options = [
            'tools' => [
                ['name' => 'get_weather', 'description' => 'Get weather'],
            ],
            PlatformSubscriber::RESPONSE_FORMAT => [
                'json_schema' => [
                    'schema' => ['type' => 'object'],
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The Gemini API does not support function calling with JSON response format');

        $client->request(new Gemini('gemini-2.0-flash'), $payload, $options);
    }

    public function testItAllowsToolsWithoutJsonResponseFormat()
    {
        $httpClient = new MockHttpClient(
            new JsonMockResponse(['candidates' => []]),
        );
        $client = new ModelClient($httpClient, 'test-api-key');

        $payload = [
            'contents' => [
                ['parts' => [['text' => 'Hello']]],
            ],
        ];

        $options = [
            'tools' => [
                ['name' => 'get_weather', 'description' => 'Get weather'],
            ],
        ];

        $result = $client->request(new Gemini('gemini-2.0-flash'), $payload, $options);

        $this->assertNotNull($result);
    }

    public function testItAllowsJsonResponseFormatWithoutTools()
    {
        $httpClient = new MockHttpClient(
            new JsonMockResponse(['candidates' => []]),
        );
        $client = new ModelClient($httpClient, 'test-api-key');

        $payload = [
            'contents' => [
                ['parts' => [['text' => 'Hello']]],
            ],
        ];

        $options = [
            PlatformSubscriber::RESPONSE_FORMAT => [
                'json_schema' => [
                    'schema' => ['type' => 'object'],
                ],
            ],
        ];

        $result = $client->request(new Gemini('gemini-2.0-flash'), $payload, $options);

        $this->assertNotNull($result);
    }
}
