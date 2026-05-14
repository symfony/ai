<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\VertexAi\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\VertexAi\Factory;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * Verifies that the Vertex AI bridge reuses the Gemini-bridge GenerateContentHandler
 * with only the URL prefix and the structured-output key differing — the
 * "same contract, two transports" duplication-collapse case from the plan.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ContractDispatcherIntegrationTest extends TestCase
{
    public function testVertexAiGenerateContentReusesGeminiHandler()
    {
        $capturedUrl = null;

        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$capturedUrl) {
            $capturedUrl = $url;

            return new JsonMockResponse([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'hello from vertex']]],
                ]],
            ]);
        });

        $platform = Factory::createPlatform(apiKey: 'test-key', httpClient: $httpClient);
        $result = $platform->invoke('gemini-2.5-flash', ['contents' => [['role' => 'user', 'parts' => [['text' => 'hi']]]]]);

        $this->assertStringStartsWith('https://aiplatform.googleapis.com/v1/publishers/google/models/gemini-2.5-flash:generateContent', $capturedUrl);
        $this->assertStringContainsString('key=test-key', $capturedUrl);

        $textResult = $result->getResult();
        $this->assertInstanceOf(TextResult::class, $textResult);
        $this->assertSame('hello from vertex', $textResult->getContent());
    }

    public function testVertexAiPredictEmbeddings()
    {
        $capturedUrl = null;
        $capturedBody = null;

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedUrl, &$capturedBody) {
            $capturedUrl = $url;
            $capturedBody = json_decode($options['body'], true);

            return new JsonMockResponse([
                'predictions' => [
                    ['embeddings' => ['values' => [0.5, 0.6]]],
                ],
            ]);
        });

        $platform = Factory::createPlatform(apiKey: 'test-key', httpClient: $httpClient);
        $result = $platform->invoke('text-embedding-005', ['hello']);

        $this->assertStringStartsWith('https://aiplatform.googleapis.com/v1/publishers/google/models/text-embedding-005:predict', $capturedUrl);
        $this->assertCount(1, $capturedBody['instances']);
        $this->assertSame('hello', $capturedBody['instances'][0]['content']);

        $vectorResult = $result->getResult();
        $this->assertInstanceOf(VectorResult::class, $vectorResult);
        $this->assertCount(1, $vectorResult->getContent());
    }

    public function testVertexAiUsesProjectScopedUrlWhenLocationGiven()
    {
        $capturedUrl = null;

        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$capturedUrl) {
            $capturedUrl = $url;

            return new JsonMockResponse([
                'candidates' => [['content' => ['parts' => [['text' => 'ok']]]]],
            ]);
        });

        // Project-scoped requires google/auth in real prod but the constructor
        // check is fine when both location+projectId are passed and apiKey too.
        $platform = Factory::createPlatform('us-central1', 'my-project', 'test-key', $httpClient);
        $platform->invoke('gemini-2.5-flash', ['contents' => [['role' => 'user', 'parts' => [['text' => 'hi']]]]]);

        $this->assertStringStartsWith('https://aiplatform.googleapis.com/v1/projects/my-project/locations/us-central1/publishers/google/models/gemini-2.5-flash:generateContent', $capturedUrl);
    }
}
