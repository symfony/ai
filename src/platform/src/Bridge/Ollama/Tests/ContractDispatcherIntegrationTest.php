<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Ollama\Factory;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ContractDispatcherIntegrationTest extends TestCase
{
    public function testChatEndpointIsSelectedForChatModels()
    {
        $capturedUrl = null;

        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$capturedUrl) {
            if (str_ends_with($url, '/api/show')) {
                return new JsonMockResponse(['capabilities' => ['completion', 'tools']]);
            }

            $capturedUrl = $url;

            return new MockResponse(json_encode([
                'message' => ['content' => 'hello from llama'],
                'done' => true,
            ]));
        });

        $platform = Factory::createPlatform('http://ollama.local:11434/', null, $httpClient);
        $result = $platform->invoke('llama3', ['messages' => [['role' => 'user', 'content' => 'hi']]]);

        $this->assertSame('http://ollama.local:11434/api/chat', $capturedUrl);

        $textResult = $result->getResult();
        $this->assertInstanceOf(TextResult::class, $textResult);
        $this->assertSame('hello from llama', $textResult->getContent());
    }

    public function testEmbedEndpointIsSelectedForEmbeddingModels()
    {
        $capturedUrl = null;
        $capturedBody = null;

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedUrl, &$capturedBody) {
            if (str_ends_with($url, '/api/show')) {
                return new JsonMockResponse(['capabilities' => ['embedding']]);
            }

            $capturedUrl = $url;
            $capturedBody = json_decode($options['body'], true);

            return new MockResponse(json_encode([
                'embeddings' => [[0.1, 0.2, 0.3]],
            ]));
        });

        $platform = Factory::createPlatform('http://ollama.local:11434/', null, $httpClient);
        $result = $platform->invoke('nomic-embed-text', 'hello');

        $this->assertSame('http://ollama.local:11434/api/embed', $capturedUrl);
        $this->assertSame('nomic-embed-text', $capturedBody['model']);
        $this->assertSame('hello', $capturedBody['input']);

        $vectorResult = $result->getResult();
        $this->assertInstanceOf(VectorResult::class, $vectorResult);
        $this->assertCount(1, $vectorResult->getContent());
    }
}
