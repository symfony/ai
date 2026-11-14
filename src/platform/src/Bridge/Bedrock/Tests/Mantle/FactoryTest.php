<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Tests\Mantle;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Bedrock\Mantle\Factory;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

/**
 * @author asrar <aszenz@gmail.com>
 */
final class FactoryTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideApis(): iterable
    {
        yield 'completions' => ['completions'];
        yield 'responses' => ['responses'];
        yield 'messages' => ['messages'];
    }

    #[DataProvider('provideApis')]
    public function testItCreatesPlatformForEveryApi(string $api)
    {
        $platform = Factory::createPlatform('bedrock-api-key', 'us-west-2', $api, null, new MockHttpClient());

        $this->assertInstanceOf(Platform::class, $platform);
    }

    #[DataProvider('provideApis')]
    public function testItCreatesPlatformWithoutApiKeyForSigV4Authentication(string $api)
    {
        $platform = Factory::createPlatform(null, 'us-west-2', $api, null, new MockHttpClient());

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testItCreatesPlatformWithEventSourceHttpClient()
    {
        $platform = Factory::createPlatform('bedrock-api-key', 'us-west-2', 'completions', null, new EventSourceHttpClient(new MockHttpClient()));

        $this->assertInstanceOf(Platform::class, $platform);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideUnknownApis(): iterable
    {
        yield 'unsupported protocol' => ['embeddings'];
        yield 'empty' => [''];
    }

    /**
     * The bundle passes this straight through from configuration, so the guard has to hold at
     * runtime even though the signature narrows it for static analysis.
     */
    #[DataProvider('provideUnknownApis')]
    public function testItThrowsOnUnknownApi(string $api)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Invalid Bedrock Mantle API "%s". Supported values are "completions", "responses", "messages".', $api));

        Factory::createPlatform('bedrock-api-key', 'us-west-2', $api);
    }

    public function testItThrowsWhenApiKeyIsEmpty()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The Bedrock API key must not be empty.');

        Factory::createPlatform('');
    }

    public function testItThrowsWhenRegionIsEmpty()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The region must not be empty.');

        Factory::createPlatform('bedrock-api-key', '');
    }

    public function testItThrowsWhenMessagesOnlyArgumentsAreUsedOnAnotherApi()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "cacheRetention" and "workspace" arguments are only supported by the Bedrock Mantle Messages API.');

        Factory::createPlatform('bedrock-api-key', 'us-west-2', 'responses', null, null, null, null, null, null, 'proj_example');
    }

    public function testItThrowsWhenPathIsUsedOnTheMessagesApi()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "path" argument is not supported by the Bedrock Mantle Messages API, which is served on a single path.');

        Factory::createPlatform('bedrock-api-key', 'us-west-2', 'messages', null, null, null, null, null, null, null, '/anthropic/v2/messages');
    }

    public function testItSendsCompletionsRequestToTheMantleEndpointForTheGivenRegion()
    {
        $responseCallback = function (string $method, string $url, array $options): HttpResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://bedrock-mantle.eu-central-1.api.aws/v1/chat/completions', $url);
            $this->assertSame('Authorization: Bearer bedrock-api-key', $options['normalized_headers']['authorization'][0]);
            $this->assertStringContainsString('"model":"openai.gpt-oss-120b"', $options['body']);

            return new MockResponse(json_encode([
                'choices' => [[
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => 'Hello!'],
                    'finish_reason' => 'stop',
                ]],
            ]));
        };

        $platform = Factory::createPlatform('bedrock-api-key', 'eu-central-1', 'completions', null, new MockHttpClient($responseCallback));

        $platform->invoke('openai.gpt-oss-120b', new MessageBag(Message::ofUser('Hello')))->getResult();
    }

    public function testItSendsCompletionsRequestToAnOverriddenPath()
    {
        $responseCallback = function (string $method, string $url, array $options): HttpResponse {
            // Gemma is served on the "/openai/v1" prefix instead, and is reachable by overriding
            // the path together with a catalog that knows the model.
            $this->assertSame('https://bedrock-mantle.us-east-1.api.aws/openai/v1/chat/completions', $url);

            return new MockResponse(json_encode([
                'choices' => [[
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => 'Hello!'],
                    'finish_reason' => 'stop',
                ]],
            ]));
        };

        $platform = Factory::createPlatform(
            'bedrock-api-key',
            'us-east-1',
            'completions',
            null,
            new MockHttpClient($responseCallback),
            null,
            null,
            null,
            null,
            null,
            '/openai/v1/chat/completions',
        );

        $platform->invoke('openai.gpt-oss-120b', new MessageBag(Message::ofUser('Hello')))->getResult();
    }

    public function testItSendsResponsesRequestToTheResponsesPath()
    {
        $responseCallback = function (string $method, string $url, array $options): HttpResponse {
            $this->assertSame('https://bedrock-mantle.us-east-1.api.aws/openai/v1/responses', $url);
            $this->assertStringContainsString('"model":"google.gemma-4-31b"', $options['body']);

            return new MockResponse(json_encode([
                'id' => 'resp_1',
                'status' => 'completed',
                'output' => [[
                    'type' => 'message',
                    'role' => 'assistant',
                    'content' => [['type' => 'output_text', 'text' => 'Hello!']],
                ]],
            ]));
        };

        $platform = Factory::createPlatform('bedrock-api-key', 'us-east-1', 'responses', null, new MockHttpClient($responseCallback));

        $platform->invoke('google.gemma-4-31b', new MessageBag(Message::ofUser('Hello')))->getResult();
    }

    public function testItSendsResponsesRequestToAnOverriddenPath()
    {
        $responseCallback = function (string $method, string $url, array $options): HttpResponse {
            $this->assertSame('https://bedrock-mantle.us-east-1.api.aws/v1/responses', $url);

            return new MockResponse(json_encode([
                'id' => 'resp_1',
                'status' => 'completed',
                'output' => [[
                    'type' => 'message',
                    'role' => 'assistant',
                    'content' => [['type' => 'output_text', 'text' => 'Hello!']],
                ]],
            ]));
        };

        $platform = Factory::createPlatform('bedrock-api-key', 'us-east-1', 'responses', null, new MockHttpClient($responseCallback), null, null, null, null, null, '/v1/responses');

        $platform->invoke('google.gemma-4-31b', new MessageBag(Message::ofUser('Hello')))->getResult();
    }

    public function testItSendsMessagesRequestToTheAnthropicPath()
    {
        $responseCallback = function (string $method, string $url, array $options): HttpResponse {
            $this->assertSame('https://bedrock-mantle.us-east-1.api.aws/anthropic/v1/messages', $url);
            $this->assertSame('x-api-key: bedrock-api-key', $options['normalized_headers']['x-api-key'][0]);
            $this->assertSame('anthropic-version: 2023-06-01', $options['normalized_headers']['anthropic-version'][0]);
            $this->assertStringContainsString('"model":"anthropic.claude-haiku-4-5"', $options['body']);

            return new MockResponse(json_encode([
                'id' => 'msg_1',
                'type' => 'message',
                'role' => 'assistant',
                'content' => [['type' => 'text', 'text' => 'Hello!']],
                'stop_reason' => 'end_turn',
            ]));
        };

        $platform = Factory::createPlatform('bedrock-api-key', 'us-east-1', 'messages', null, new MockHttpClient($responseCallback));

        $platform->invoke('anthropic.claude-haiku-4-5', new MessageBag(Message::ofUser('Hello')))->getResult();
    }

    public function testItDefaultsMessagesCacheRetentionToShort()
    {
        $responseCallback = function (string $method, string $url, array $options): HttpResponse {
            $body = json_decode($options['body'], true, flags: \JSON_THROW_ON_ERROR);

            // "short" is the default and caches without the extended-TTL marker that "long" adds.
            $this->assertSame(['type' => 'ephemeral'], $body['messages'][0]['content'][0]['cache_control']);

            return new MockResponse(json_encode([
                'id' => 'msg_1',
                'type' => 'message',
                'role' => 'assistant',
                'content' => [['type' => 'text', 'text' => 'Hello!']],
                'stop_reason' => 'end_turn',
            ]));
        };

        $platform = Factory::createPlatform('bedrock-api-key', 'us-east-1', 'messages', null, new MockHttpClient($responseCallback));

        $platform->invoke('anthropic.claude-haiku-4-5', new MessageBag(Message::ofUser('Hello')))->getResult();
    }
}
