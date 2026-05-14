<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Gemini\Factory;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ContractDispatcherIntegrationTest extends TestCase
{
    public function testGeminiGenerateContent()
    {
        $capturedUrl = null;
        $capturedHeaders = null;

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedUrl, &$capturedHeaders) {
            $capturedUrl = $url;
            $capturedHeaders = self::parseHeaders($options['headers']);

            return new JsonMockResponse([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'hello from gemini']]],
                ]],
                'usageMetadata' => ['promptTokenCount' => 4, 'candidatesTokenCount' => 3],
            ]);
        });

        $platform = Factory::createPlatform('test-key', $httpClient);
        $result = $platform->invoke('gemini-2.5-flash', ['contents' => [['role' => 'user', 'parts' => [['text' => 'hi']]]]]);

        $this->assertSame('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent', $capturedUrl);
        $this->assertSame('test-key', $capturedHeaders['x-goog-api-key']);

        $textResult = $result->getResult();
        $this->assertInstanceOf(TextResult::class, $textResult);
        $this->assertSame('hello from gemini', $textResult->getContent());
    }

    public function testGeminiBatchEmbedContents()
    {
        $capturedUrl = null;
        $capturedBody = null;

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedUrl, &$capturedBody) {
            $capturedUrl = $url;
            $capturedBody = json_decode($options['body'], true);

            return new JsonMockResponse([
                'embeddings' => [
                    ['values' => [0.1, 0.2]],
                    ['values' => [0.3, 0.4]],
                ],
            ]);
        });

        $platform = Factory::createPlatform('test-key', $httpClient);
        $result = $platform->invoke('gemini-embedding-001', ['hello', 'world']);

        $this->assertSame('https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:batchEmbedContents', $capturedUrl);
        $this->assertCount(2, $capturedBody['requests']);

        $vectorResult = $result->getResult();
        $this->assertInstanceOf(VectorResult::class, $vectorResult);
        $this->assertCount(2, $vectorResult->getContent());
    }

    /**
     * @param list<string> $rawHeaders
     *
     * @return array<string, string>
     */
    private static function parseHeaders(array $rawHeaders): array
    {
        $headers = [];
        foreach ($rawHeaders as $header) {
            [$name, $value] = explode(': ', $header, 2);
            $headers[strtolower($name)] = $value;
        }

        return $headers;
    }
}
