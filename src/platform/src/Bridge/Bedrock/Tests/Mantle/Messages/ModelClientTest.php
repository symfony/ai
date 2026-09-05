<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Tests\Mantle\Messages;

use AsyncAws\Core\Configuration;
use AsyncAws\Core\Credentials\CredentialProvider;
use AsyncAws\Core\Credentials\Credentials;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\Bedrock\Mantle\Messages\ModelClient;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

/**
 * @author asrar <aszenz@gmail.com>
 */
final class ModelClientTest extends TestCase
{
    public function testItSupportsClaudeModels()
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'https://bedrock-mantle.us-east-1.api.aws', 'us-east-1', 'bedrock-api-key');

        $this->assertTrue($modelClient->supports(new Claude('anthropic.claude-haiku-4-5')));
    }

    public function testItAuthenticatesWithApiKeyAndWorkspaceHeaders()
    {
        $responseCallback = function (string $method, string $url, array $options): HttpResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://bedrock-mantle.us-east-1.api.aws/anthropic/v1/messages', $url);
            $this->assertSame('x-api-key: bedrock-api-key', $options['normalized_headers']['x-api-key'][0]);
            $this->assertSame('anthropic-version: 2023-06-01', $options['normalized_headers']['anthropic-version'][0]);
            $this->assertSame('anthropic-workspace: proj_example', $options['normalized_headers']['anthropic-workspace'][0]);
            $this->assertSame('{"model":"anthropic.claude-haiku-4-5","messages":[{"role":"user","content":"Hello"}]}', $options['body']);

            return new MockResponse();
        };

        $modelClient = new ModelClient(new MockHttpClient($responseCallback), 'https://bedrock-mantle.us-east-1.api.aws', 'us-east-1', 'bedrock-api-key', cacheRetention: 'none', workspace: 'proj_example');
        $modelClient->request(new Claude('anthropic.claude-haiku-4-5'), ['messages' => [['role' => 'user', 'content' => 'Hello']]]);
    }

    public function testItSignsAnthropicHeadersWithSigV4()
    {
        $responseCallback = function (string $method, string $url, array $options): HttpResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://bedrock-mantle.eu-central-1.api.aws/anthropic/v1/messages', $url);
            $this->assertSame('anthropic-version: 2023-06-01', $options['normalized_headers']['anthropic-version'][0]);

            $authorization = $options['normalized_headers']['authorization'][0];
            $this->assertStringContainsString('AWS4-HMAC-SHA256 Credential=AKIDEXAMPLE/', $authorization);
            $this->assertStringContainsString('/eu-central-1/bedrock/aws4_request', $authorization);
            $this->assertStringContainsString('anthropic-version', $authorization);
            $this->assertArrayHasKey('x-amz-date', $options['normalized_headers']);

            return new MockResponse();
        };

        $modelClient = new ModelClient(
            new MockHttpClient($responseCallback),
            'https://bedrock-mantle.eu-central-1.api.aws',
            'eu-central-1',
            null,
            $this->staticCredentialProvider(),
            'none',
        );
        $modelClient->request(new Claude('anthropic.claude-haiku-4-5'), ['messages' => []]);
    }

    public function testItAddsPromptCachingMarkers()
    {
        $responseCallback = function (string $method, string $url, array $options): HttpResponse {
            $body = json_decode($options['body'], true, flags: \JSON_THROW_ON_ERROR);

            $this->assertSame(['type' => 'ephemeral', 'ttl' => '1h'], $body['messages'][0]['content'][0]['cache_control']);
            $this->assertSame(['type' => 'ephemeral', 'ttl' => '1h'], $body['system'][0]['cache_control']);
            $this->assertSame(['type' => 'ephemeral', 'ttl' => '1h'], $body['tools'][0]['cache_control']);

            return new MockResponse();
        };

        $modelClient = new ModelClient(new MockHttpClient($responseCallback), 'https://bedrock-mantle.us-east-1.api.aws', 'us-east-1', 'bedrock-api-key', cacheRetention: 'long');
        $modelClient->request(new Claude('anthropic.claude-sonnet-5'), [
            'system' => [['type' => 'text', 'text' => 'Be helpful']],
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ], [
            'tools' => [['name' => 'weather', 'description' => 'Weather', 'input_schema' => ['type' => 'object']]],
        ]);
    }

    public function testItRejectsStructuredOutput()
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'https://bedrock-mantle.us-east-1.api.aws', 'us-east-1', 'bedrock-api-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Structured outputs are not supported by the Anthropic Messages API on the Bedrock Mantle endpoint.');

        $modelClient->request(new Claude('anthropic.claude-haiku-4-5'), ['messages' => []], ['response_format' => ['type' => 'json_schema']]);
    }

    public function testItRejectsStructuredOutputInPayload()
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'https://bedrock-mantle.us-east-1.api.aws', 'us-east-1', 'bedrock-api-key');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Structured outputs are not supported by the Anthropic Messages API on the Bedrock Mantle endpoint.');

        $modelClient->request(new Claude('anthropic.claude-haiku-4-5'), ['messages' => [], 'output_config' => ['format' => ['type' => 'json_schema']]]);
    }

    public function testItRejectsStringPayload()
    {
        $modelClient = new ModelClient(new MockHttpClient(), 'https://bedrock-mantle.us-east-1.api.aws', 'us-east-1', 'bedrock-api-key');

        $this->expectException(InvalidArgumentException::class);

        $modelClient->request(new Claude('anthropic.claude-haiku-4-5'), 'payload');
    }

    private function staticCredentialProvider(): CredentialProvider
    {
        return new class implements CredentialProvider {
            public function getCredentials(Configuration $configuration): Credentials
            {
                return new Credentials('AKIDEXAMPLE', 'wJalrXUtnFEMI/K7MDENG+bPxRfiCYEXAMPLEKEY');
            }
        };
    }
}
