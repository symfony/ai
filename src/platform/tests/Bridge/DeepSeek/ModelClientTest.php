<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\DeepSeek;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\DeepSeek\DeepSeek;
use Symfony\AI\Platform\Bridge\DeepSeek\ModelClient;
use Symfony\AI\Platform\Model;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ModelClientTest extends TestCase
{
    public function testSupportsDeepSeekModel()
    {
        $httpClient = new MockHttpClient();
        $modelClient = new ModelClient($httpClient, 'test-api-key');

        $model = new DeepSeek('deepseek-chat');
        $this->assertTrue($modelClient->supports($model));
    }

    public function testDoesNotSupportOtherModels()
    {
        $httpClient = new MockHttpClient();
        $modelClient = new ModelClient($httpClient, 'test-api-key');

        $model = new Model('gpt-4');
        $this->assertFalse($modelClient->supports($model));
    }

    public function testRequestSendsToCorrectEndpoint()
    {
        $requestMade = false;
        $httpClient = new MockHttpClient(function ($method, $url, $options) use (&$requestMade) {
            $requestMade = true;
            self::assertSame('POST', $method);
            self::assertSame('https://api.deepseek.com/chat/completions', $url);
            self::assertArrayHasKey('normalized_headers', $options);
            self::assertArrayHasKey('authorization', $options['normalized_headers']);
            self::assertSame('Authorization: Bearer test-api-key', $options['normalized_headers']['authorization'][0]);

            return new JsonMockResponse(['choices' => [['message' => ['content' => 'Hello'], 'finish_reason' => 'stop']]]);
        });

        $modelClient = new ModelClient($httpClient, 'test-api-key');
        $model = new DeepSeek('deepseek-chat');

        $modelClient->request($model, ['messages' => [['role' => 'user', 'content' => 'Hi']]]);
        $this->assertTrue($requestMade);
    }

    public function testRequestMergesOptionsWithPayload()
    {
        $requestMade = false;
        $httpClient = new MockHttpClient(function ($method, $url, $options) use (&$requestMade) {
            $requestMade = true;
            $body = json_decode($options['body'], true);
            self::assertArrayHasKey('messages', $body);
            self::assertArrayHasKey('temperature', $body);
            self::assertSame(0.7, $body['temperature']);

            return new JsonMockResponse(['choices' => [['message' => ['content' => 'Hello'], 'finish_reason' => 'stop']]]);
        });

        $modelClient = new ModelClient($httpClient, 'test-api-key');
        $model = new DeepSeek('deepseek-chat');

        $modelClient->request(
            $model,
            ['messages' => [['role' => 'user', 'content' => 'Hi']]],
            ['temperature' => 0.7]
        );
        $this->assertTrue($requestMade);
    }
}
