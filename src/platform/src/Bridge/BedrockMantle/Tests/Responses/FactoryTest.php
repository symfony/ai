<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\BedrockMantle\Tests\Responses;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\BedrockMantle\Responses\Factory;
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

    public function testItSendsRequestToTheMantleResponsesEndpointForTheGivenRegion()
    {
        $responseCallback = static function (string $method, string $url, array $options): HttpResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://bedrock-mantle.eu-central-1.api.aws/openai/v1/responses', $url);
            self::assertSame('Authorization: Bearer bedrock-api-key', $options['normalized_headers']['authorization'][0]);
            self::assertStringContainsString('"model":"google.gemma-4-31b"', $options['body']);

            return new MockResponse(json_encode([
                'output' => [[
                    'type' => 'message',
                    'id' => 'msg_1',
                    'role' => 'assistant',
                    'content' => [['type' => 'output_text', 'text' => 'Hello!']],
                ]],
            ]));
        };

        $platform = Factory::createPlatform('bedrock-api-key', 'eu-central-1', httpClient: new MockHttpClient($responseCallback));

        $result = $platform->invoke('google.gemma-4-31b', new MessageBag(Message::ofUser('Hello')))->getResult();

        $this->assertSame('Hello!', $result->getContent());
    }
}
