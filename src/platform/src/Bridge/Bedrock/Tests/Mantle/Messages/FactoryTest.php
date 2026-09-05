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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Bedrock\Mantle\Messages\Factory;
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
    public function testItCreatesPlatform()
    {
        $platform = Factory::createPlatform('bedrock-api-key', httpClient: new MockHttpClient());

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testItCreatesPlatformWithEventSourceHttpClient()
    {
        $platform = Factory::createPlatform('bedrock-api-key', httpClient: new EventSourceHttpClient(new MockHttpClient()));

        $this->assertInstanceOf(Platform::class, $platform);
    }

    public function testItCreatesPlatformWithoutApiKeyForSigV4Authentication()
    {
        $platform = Factory::createPlatform(httpClient: new MockHttpClient());

        $this->assertInstanceOf(Platform::class, $platform);
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

    public function testItThrowsWhenWorkspaceIsEmpty()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The Bedrock Mantle workspace must not be empty.');

        Factory::createPlatform('bedrock-api-key', workspace: '');
    }

    public function testItUsesTheAnthropicContractAndResultConverter()
    {
        $responseCallback = function (string $method, string $url, array $options): HttpResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://bedrock-mantle.eu-central-1.api.aws/anthropic/v1/messages', $url);
            $this->assertSame('x-api-key: bedrock-api-key', $options['normalized_headers']['x-api-key'][0]);
            $this->assertSame('anthropic-version: 2023-06-01', $options['normalized_headers']['anthropic-version'][0]);
            $this->assertStringContainsString('"model":"anthropic.claude-haiku-4-5"', $options['body']);
            $this->assertStringContainsString('"messages":[{"role":"user"', $options['body']);

            return new MockResponse(json_encode([
                'id' => 'msg_1',
                'type' => 'message',
                'role' => 'assistant',
                'content' => [['type' => 'text', 'text' => 'Hello!']],
                'stop_reason' => 'end_turn',
                'usage' => ['input_tokens' => 4, 'output_tokens' => 2],
            ]));
        };

        $platform = Factory::createPlatform('bedrock-api-key', 'eu-central-1', httpClient: new MockHttpClient($responseCallback), cacheRetention: 'none');
        $result = $platform->invoke('anthropic.claude-haiku-4-5', new MessageBag(Message::ofUser('Hello')))->getResult();

        $this->assertSame('Hello!', $result->getContent());
    }
}
