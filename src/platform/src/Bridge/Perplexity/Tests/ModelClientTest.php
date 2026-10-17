<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Perplexity\Tests;

use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenResponses\ResponsesModel;
use Symfony\AI\Platform\Bridge\Perplexity\ModelClient;
use Symfony\AI\Platform\Bridge\Perplexity\Perplexity;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class ModelClientTest extends TestCase
{
    public function testItThrowsExceptionWhenApiKeyIsEmpty()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The API key must not be empty.');

        new ModelClient(new MockHttpClient(), '');
    }

    #[TestWith(['api-key-without-prefix'])]
    #[TestWith(['plx-api-key'])]
    #[TestWith(['PPLX-api-key'])]
    #[TestWith(['pplxapikey'])]
    #[TestWith(['pplx api-key'])]
    #[TestWith(['pplx'])]
    public function testItThrowsExceptionWhenApiKeyDoesNotStartWithPplx(string $invalidApiKey)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The API key must start with "pplx-".');

        new ModelClient(new MockHttpClient(), $invalidApiKey);
    }

    public function testItAcceptsValidApiKey()
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'pplx-valid-api-key');

        $this->assertInstanceOf(ModelClient::class, $modelClient);
    }

    public function testItWrapsHttpClientInEventSourceHttpClient()
    {
        $httpClient = new MockHttpClient();
        $modelClient = new ModelClient($httpClient, 'pplx-valid-api-key');

        $this->assertInstanceOf(ModelClient::class, $modelClient);
    }

    public function testItAcceptsEventSourceHttpClientDirectly()
    {
        $httpClient = new EventSourceHttpClient(new MockHttpClient());
        $modelClient = new ModelClient($httpClient, 'pplx-valid-api-key');

        $this->assertInstanceOf(ModelClient::class, $modelClient);
    }

    public function testItIsSupportingTheCorrectModel()
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'pplx-api-key');

        $this->assertTrue($modelClient->supports(new Perplexity('fast')));
        $this->assertFalse($modelClient->supports(new ResponsesModel('fast')));
    }

    public function testItSendsAPresetToTheAgentEndpoint()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.perplexity.ai/v1/agent', $url);
            self::assertSame('Authorization: Bearer pplx-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertSame('{"preset":"fast","input":[{"role":"user","content":"test message"}]}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'pplx-api-key');
        $modelClient->request(new Perplexity('fast'), ['input' => [['role' => 'user', 'content' => 'test message']]]);
    }

    public function testItSendsAProviderPrefixedModelAsModel()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('{"tools":[{"type":"web_search"}],"model":"perplexity\/sonar","input":[{"role":"user","content":"Hello"}]}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'pplx-api-key');
        $modelClient->request(new Perplexity('perplexity/sonar'), ['input' => [['role' => 'user', 'content' => 'Hello']]], ['tools' => [['type' => 'web_search']]]);
    }

    #[IgnoreDeprecations]
    #[TestWith(['sonar', 'fast'])]
    #[TestWith(['sonar-pro', 'fast'])]
    #[TestWith(['sonar-reasoning', 'low'])]
    #[TestWith(['sonar-reasoning-pro', 'low'])]
    #[TestWith(['sonar-deep-research', 'high'])]
    public function testItSendsDeprecatedSonarModelsAsTheirReplacementPreset(string $model, string $preset)
    {
        $resultCallback = static function (string $method, string $url, array $options) use ($preset): HttpResponse {
            self::assertSame('{"preset":"'.$preset.'","input":"Hello"}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'pplx-api-key');

        $this->expectUserDeprecationMessage(\sprintf('Since symfony/ai-perplexity-platform 0.15: The "%s" model is deprecated, use the "%s" preset instead.', $model, $preset));

        $modelClient->request(new Perplexity($model), ['input' => 'Hello']);
    }

    public function testItRenamesMaxTokensToMaxOutputTokens()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('{"max_output_tokens":500,"preset":"fast","input":"Hello"}', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'pplx-api-key');
        $modelClient->request(new Perplexity('fast'), ['input' => 'Hello'], ['max_tokens' => 500]);
    }

    public function testItKeepsTheResponseFormatAtTheTopLevel()
    {
        $responseFormat = [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'Capital',
                'schema' => ['type' => 'object', 'properties' => ['city' => ['type' => 'string']]],
                'strict' => true,
            ],
        ];

        $resultCallback = static function (string $method, string $url, array $options) use ($responseFormat): HttpResponse {
            $body = json_decode($options['body'], true);

            self::assertSame($responseFormat, $body['response_format']);
            self::assertArrayNotHasKey('text', $body);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'pplx-api-key');
        $modelClient->request(new Perplexity('perplexity/sonar'), ['input' => 'Hello'], ['response_format' => $responseFormat]);
    }

    public function testCustomBaseUrlIsUsedAndTrailingSlashNormalized()
    {
        $resultCallback = static function (string $method, string $url): HttpResponse {
            self::assertSame('https://perplexity.example.com/v1/agent', $url);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'pplx-api-key', 'https://perplexity.example.com/');
        $modelClient->request(new Perplexity('fast'), ['input' => 'Hello']);
    }

    public function testMalformedUtf8InPayloadDoesNotAbortTheRequest()
    {
        $resultCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('Content-Type: application/json', $options['normalized_headers']['content-type'][0]);
            self::assertJson($options['body']);
            self::assertStringContainsString('tool output \ufffd here', $options['body']);

            return new MockResponse();
        };
        $httpClient = new MockHttpClient([$resultCallback]);
        $modelClient = new ModelClient($httpClient, 'pplx-api-key');
        $modelClient->request(new Perplexity('fast'), ['input' => [['role' => 'user', 'content' => "tool output \xB1 here"]]]);
    }
}
