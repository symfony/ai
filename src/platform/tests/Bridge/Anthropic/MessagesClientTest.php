<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Anthropic;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\Anthropic\MessagesClient;
use Symfony\AI\Platform\Bridge\Anthropic\Transport\HttpTransport;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Hannes Kandulla <hannes@faibl.org>
 */
final class MessagesClientTest extends TestCase
{
    public function testToolsCacheControlIsInjectedWithShortRetention()
    {
        $capturedBody = null;

        $httpClient = new MockHttpClient(static function ($method, $url, $options) use (&$capturedBody) {
            $capturedBody = json_decode($options['body'], true);

            return new MockResponse(json_encode([
                'type' => 'message',
                'content' => [['type' => 'text', 'text' => 'Hello']],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
            ]));
        });

        $client = new MessagesClient(new HttpTransport($httpClient, 'test-api-key'), 'short');

        $tools = [
            ['name' => 'tool_a', 'description' => 'First tool', 'input_schema' => ['type' => 'object']],
            ['name' => 'tool_b', 'description' => 'Second tool', 'input_schema' => ['type' => 'object']],
        ];

        $payload = [
            'model' => Claude::SONNET_4,
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ];

        $client->request(new Claude(Claude::SONNET_4), $payload, ['tools' => $tools]);

        $this->assertNotNull($capturedBody);

        // Last tool should have cache_control
        $this->assertArrayHasKey('cache_control', $capturedBody['tools'][1]);
        $this->assertSame(['type' => 'ephemeral'], $capturedBody['tools'][1]['cache_control']);

        // First tool should NOT have cache_control
        $this->assertArrayNotHasKey('cache_control', $capturedBody['tools'][0]);
    }

    public function testToolsCacheControlIsInjectedWithLongRetention()
    {
        $capturedBody = null;

        $httpClient = new MockHttpClient(static function ($method, $url, $options) use (&$capturedBody) {
            $capturedBody = json_decode($options['body'], true);

            return new MockResponse(json_encode([
                'type' => 'message',
                'content' => [['type' => 'text', 'text' => 'Hello']],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
            ]));
        });

        $client = new MessagesClient(new HttpTransport($httpClient, 'test-api-key'), 'long');

        $tools = [
            ['name' => 'tool_a', 'description' => 'A tool', 'input_schema' => ['type' => 'object']],
        ];

        $payload = [
            'model' => Claude::SONNET_4,
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ];

        $client->request(new Claude(Claude::SONNET_4), $payload, ['tools' => $tools]);

        $this->assertNotNull($capturedBody);
        $this->assertSame(['type' => 'ephemeral', 'ttl' => '1h'], $capturedBody['tools'][0]['cache_control']);
    }

    public function testToolsCacheControlIsNotInjectedWithNoneRetention()
    {
        $capturedBody = null;

        $httpClient = new MockHttpClient(static function ($method, $url, $options) use (&$capturedBody) {
            $capturedBody = json_decode($options['body'], true);

            return new MockResponse(json_encode([
                'type' => 'message',
                'content' => [['type' => 'text', 'text' => 'Hello']],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
            ]));
        });

        $client = new MessagesClient(new HttpTransport($httpClient, 'test-api-key'), 'none');

        $tools = [
            ['name' => 'tool_a', 'description' => 'A tool', 'input_schema' => ['type' => 'object']],
        ];

        $payload = [
            'model' => Claude::SONNET_4,
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ];

        $client->request(new Claude(Claude::SONNET_4), $payload, ['tools' => $tools]);

        $this->assertNotNull($capturedBody);
        $this->assertArrayNotHasKey('cache_control', $capturedBody['tools'][0]);
    }
}
