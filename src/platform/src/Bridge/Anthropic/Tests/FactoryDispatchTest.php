<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\Factory;
use Symfony\AI\Platform\Bridge\Anthropic\MessagesClient;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * End-to-end test for the {@see MessagesClient} dispatch path that
 * {@see Factory} now wires up by default.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class FactoryDispatchTest extends TestCase
{
    public function testInvokeRoutesThroughMessagesClient()
    {
        $capturedUrl = null;
        $capturedHeaders = null;
        $capturedBody = null;

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedUrl, &$capturedHeaders, &$capturedBody) {
            $capturedUrl = $url;
            $capturedHeaders = $this->parseHeaders($options['headers']);
            $capturedBody = json_decode($options['body'], true);

            return new JsonMockResponse([
                'content' => [['type' => 'text', 'text' => 'hello back']],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 4],
            ]);
        });

        $platform = Factory::createPlatform('test-api-key', $httpClient);

        $result = $platform->invoke('claude-3-7-sonnet-latest', new MessageBag(Message::ofUser('hi')));

        $this->assertSame('https://api.anthropic.com/v1/messages', $capturedUrl);
        $this->assertSame('test-api-key', $capturedHeaders['x-api-key']);
        $this->assertSame('2023-06-01', $capturedHeaders['anthropic-version']);
        $this->assertSame('claude-3-7-sonnet-latest', $capturedBody['model']);

        // Default cache retention of 'short' should add an ephemeral cache_control marker.
        $lastUserContent = $capturedBody['messages'][0]['content'];
        $this->assertSame('ephemeral', $lastUserContent[\count($lastUserContent) - 1]['cache_control']['type']);

        $textResult = $result->getResult();
        $this->assertInstanceOf(TextResult::class, $textResult);
        $this->assertSame('hello back', $textResult->getContent());
    }

    public function testInvokeRespectsExplicitEndpointOption()
    {
        $reached = false;

        $httpClient = new MockHttpClient(static function () use (&$reached) {
            $reached = true;

            return new JsonMockResponse([
                'content' => [['type' => 'text', 'text' => 'ok']],
            ]);
        });

        $platform = Factory::createPlatform('test-api-key', $httpClient);

        $platform->invoke(
            'claude-3-7-sonnet-latest',
            new MessageBag(Message::ofUser('hi')),
            ['endpoint' => MessagesClient::ENDPOINT],
        );

        $this->assertTrue($reached, 'Request was not sent through the dispatcher when endpoint was explicitly named');
    }

    public function testInvokeWithUnknownEndpointThrows()
    {
        $httpClient = new MockHttpClient(static fn () => new JsonMockResponse(['content' => []]));
        $platform = Factory::createPlatform('test-api-key', $httpClient);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/No ModelClient registered for model "claude-3-7-sonnet-latest"/');

        $platform->invoke(
            'claude-3-7-sonnet-latest',
            new MessageBag(Message::ofUser('hi')),
            ['endpoint' => 'openai.chat_completions'],
        );
    }

    /**
     * @param list<string> $rawHeaders
     *
     * @return array<string, string>
     */
    private function parseHeaders(array $rawHeaders): array
    {
        $headers = [];
        foreach ($rawHeaders as $header) {
            [$name, $value] = explode(': ', $header, 2);
            $headers[strtolower($name)] = $value;
        }

        return $headers;
    }
}
