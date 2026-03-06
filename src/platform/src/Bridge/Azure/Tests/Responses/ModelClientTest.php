<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Azure\Tests\Responses;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Azure\Responses\ModelClient;
use Symfony\AI\Platform\Bridge\OpenResponses\ResponsesModel;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ModelClientTest extends TestCase
{
    public function testItThrowsExceptionWhenBaseUrlIsEmpty()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The base URL must not be empty.');

        new ModelClient(new MockHttpClient(), '', 'my-deployment', 'api-key');
    }

    #[TestWith(['http://test.openai.azure.com', 'The base URL must not contain the protocol.'])]
    #[TestWith(['https://test.openai.azure.com', 'The base URL must not contain the protocol.'])]
    #[TestWith(['http://test.openai.azure.com/openai', 'The base URL must not contain the protocol.'])]
    #[TestWith(['https://test.openai.azure.com:443', 'The base URL must not contain the protocol.'])]
    public function testItThrowsExceptionWhenBaseUrlContainsProtocol(string $invalidUrl, string $expectedMessage)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        new ModelClient(new MockHttpClient(), $invalidUrl, 'my-deployment', 'api-key');
    }

    public function testItThrowsExceptionWhenApiKeyIsEmpty()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The API key must not be empty.');

        new ModelClient(new MockHttpClient(), 'test.openai.azure.com', 'my-deployment', '');
    }

    public function testItAcceptsValidParameters()
    {
        $client = new ModelClient(new MockHttpClient(), 'test.openai.azure.com', 'my-deployment', 'valid-api-key');

        $this->assertInstanceOf(ModelClient::class, $client);
    }

    public function testItIsSupportingTheCorrectModel()
    {
        $client = new ModelClient(new MockHttpClient(), 'test.openai.azure.com', 'my-deployment', 'api-key');

        $this->assertTrue($client->supports(new ResponsesModel('gpt-4o')));
    }

    public function testItIsExecutingTheCorrectRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://test.openai.azure.com/openai/v1/responses', $url);
            self::assertSame(['api-key: test-api-key'], $options['normalized_headers']['api-key']);
            self::assertSame('{"model":"gpt-4o","input":[{"role":"user","content":"Hello"}]}', $options['body']);

            return new MockResponse();
        };

        $httpClient = new MockHttpClient([$resultCallback]);
        $client = new ModelClient($httpClient, 'test.openai.azure.com', 'gpt-4o', 'test-api-key');
        $client->request(new ResponsesModel('gpt-4o'), ['input' => [['role' => 'user', 'content' => 'Hello']]]);
    }

    public function testItUsesDeploymentNameInsteadOfModelName()
    {
        $resultCallback = static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('{"model":"my-custom-deployment","input":[{"role":"user","content":"Hello"}]}', $options['body']);

            return new MockResponse();
        };

        $httpClient = new MockHttpClient([$resultCallback]);
        $client = new ModelClient($httpClient, 'test.openai.azure.com', 'my-custom-deployment', 'test-api-key');
        $client->request(new ResponsesModel('gpt-4o'), ['input' => [['role' => 'user', 'content' => 'Hello']]]);
    }

    public function testItFallsBackToModelNameWhenDeploymentIsNull()
    {
        $resultCallback = static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('{"model":"gpt-4o","input":[{"role":"user","content":"Hello"}]}', $options['body']);

            return new MockResponse();
        };

        $httpClient = new MockHttpClient([$resultCallback]);
        $client = new ModelClient($httpClient, 'test.openai.azure.com', null, 'test-api-key');
        $client->request(new ResponsesModel('gpt-4o'), ['input' => [['role' => 'user', 'content' => 'Hello']]]);
    }
}
