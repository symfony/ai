<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Venice\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Venice\TokenUsageExtractor;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class TokenUsageExtractorTest extends TestCase
{
    public function testExtractFromCompletionResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'choices' => [
                    [
                        'message' => ['content' => 'Hello'],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 10,
                    'completion_tokens' => 5,
                    'total_tokens' => 15,
                ],
            ]),
        ], 'https://api.venice.ai/api/v1/');

        $response = $httpClient->request('POST', 'chat/completions');
        $rawResult = new RawHttpResult($response);

        $extractor = new TokenUsageExtractor();
        $tokenUsage = $extractor->extract($rawResult);

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(10, $tokenUsage->getPromptTokens());
        $this->assertSame(5, $tokenUsage->getCompletionTokens());
        $this->assertSame(15, $tokenUsage->getTotalTokens());
    }

    public function testExtractFromEmbeddingsResponse()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'data' => [
                    ['embedding' => [0.1, 0.2, 0.3]],
                ],
                'usage' => [
                    'prompt_tokens' => 8,
                    'total_tokens' => 8,
                ],
            ]),
        ], 'https://api.venice.ai/api/v1/');

        $response = $httpClient->request('POST', 'embeddings');
        $rawResult = new RawHttpResult($response);

        $extractor = new TokenUsageExtractor();
        $tokenUsage = $extractor->extract($rawResult);

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(8, $tokenUsage->getPromptTokens());
        $this->assertSame(8, $tokenUsage->getTotalTokens());
    }

    public function testExtractFromSpeechReturnsNull()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([]),
        ], 'https://api.venice.ai/api/v1/');

        $response = $httpClient->request('POST', 'audio/speech');
        $rawResult = new RawHttpResult($response);

        $extractor = new TokenUsageExtractor();
        $tokenUsage = $extractor->extract($rawResult);

        $this->assertNull($tokenUsage);
    }

    public function testExtractFromUnknownUrlReturnsNull()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([]),
        ], 'https://api.venice.ai/api/v1/');

        $response = $httpClient->request('POST', 'unknown/endpoint');
        $rawResult = new RawHttpResult($response);

        $extractor = new TokenUsageExtractor();
        $tokenUsage = $extractor->extract($rawResult);

        $this->assertNull($tokenUsage);
    }

    public function testExtractFromImageGenerateReturnsNull()
    {
        $httpClient = new MockHttpClient([new JsonMockResponse([])], 'https://api.venice.ai/api/v1/');
        $response = $httpClient->request('POST', 'image/generate');

        $this->assertNull((new TokenUsageExtractor())->extract(new RawHttpResult($response)));
    }

    public function testExtractFromTranscriptionReturnsNull()
    {
        $httpClient = new MockHttpClient([new JsonMockResponse([])], 'https://api.venice.ai/api/v1/');
        $response = $httpClient->request('POST', 'audio/transcriptions');

        $this->assertNull((new TokenUsageExtractor())->extract(new RawHttpResult($response)));
    }

    public function testExtractFromVideoRetrieveReturnsNull()
    {
        $httpClient = new MockHttpClient([new JsonMockResponse([])], 'https://api.venice.ai/api/v1/');
        $response = $httpClient->request('POST', 'video/retrieve');

        $this->assertNull((new TokenUsageExtractor())->extract(new RawHttpResult($response)));
    }

    public function testExtractFromStreamReturnsNull()
    {
        $httpClient = new MockHttpClient([new JsonMockResponse([])], 'https://api.venice.ai/api/v1/');
        $response = $httpClient->request('POST', 'chat/completions');

        $this->assertNull((new TokenUsageExtractor())->extract(new RawHttpResult($response), ['stream' => true]));
    }

    public function testExtractFromCompletionWithCachedAndReasoningTokens()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'choices' => [['message' => ['content' => 'Hi']]],
                'usage' => [
                    'prompt_tokens' => 100,
                    'completion_tokens' => 200,
                    'total_tokens' => 300,
                    'prompt_tokens_details' => ['cached_tokens' => 60],
                    'completion_tokens_details' => ['reasoning_tokens' => 50],
                ],
            ]),
        ], 'https://api.venice.ai/api/v1/');

        $response = $httpClient->request('POST', 'chat/completions');
        $tokenUsage = (new TokenUsageExtractor())->extract(new RawHttpResult($response));

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(100, $tokenUsage->getPromptTokens());
        $this->assertSame(200, $tokenUsage->getCompletionTokens());
        $this->assertSame(60, $tokenUsage->getCachedTokens());
        $this->assertSame(50, $tokenUsage->getThinkingTokens());
        $this->assertSame(300, $tokenUsage->getTotalTokens());
    }

    public function testExtractFromCompletionWithMissingUsage()
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse(['choices' => [['message' => ['content' => 'Hi']]]]),
        ], 'https://api.venice.ai/api/v1/');

        $response = $httpClient->request('POST', 'chat/completions');
        $tokenUsage = (new TokenUsageExtractor())->extract(new RawHttpResult($response));

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertNull($tokenUsage->getPromptTokens());
        $this->assertNull($tokenUsage->getCompletionTokens());
        $this->assertNull($tokenUsage->getTotalTokens());
    }
}
