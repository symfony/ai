<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\OpenAi;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt\TokenUsageExtractor;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class TokenUsageExtractorTest extends TestCase
{
    public function testItHandlesStreamResponsesWithoutProcessing()
    {
        $extractor = new TokenUsageExtractor();

        $this->assertNull($extractor->extract(new InMemoryRawResult(), ['stream' => true]));
    }

    public function testItDoesNothingWithoutUsageData()
    {
        $extractor = new TokenUsageExtractor();

        $this->assertNull($extractor->extract(new InMemoryRawResult(['some' => 'data'])));
    }

    public function testItExtractsTokenUsage()
    {
        $extractor = new TokenUsageExtractor();
        $result = new InMemoryRawResult([
            'usage' => [
                'input_tokens' => 10,
                'output_tokens' => 20,
                'total_tokens' => 50,
                'output_tokens_details' => [
                    'reasoning_tokens' => 20,
                ],
                'input_tokens_details' => [
                    'cached_tokens' => 40,
                ],
            ],
        ], object: $this->createResponseObject());

        $tokenUsage = $extractor->extract($result);

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(10, $tokenUsage->getPromptTokens());
        $this->assertSame(20, $tokenUsage->getCompletionTokens());
        $this->assertSame(1000, $tokenUsage->getRemainingTokens());
        $this->assertSame(20, $tokenUsage->getThinkingTokens());
        $this->assertSame(40, $tokenUsage->getCachedTokens());
        $this->assertSame(50, $tokenUsage->getTotalTokens());
    }

    public function testItHandlesMissingUsageFields()
    {
        $extractor = new TokenUsageExtractor();
        $result = new InMemoryRawResult([
            'usage' => [
                // Missing some fields
                'input_tokens' => 10,
            ],
        ], object: $this->createResponseObject());

        $tokenUsage = $extractor->extract($result);

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(10, $tokenUsage->getPromptTokens());
        $this->assertSame(1000, $tokenUsage->getRemainingTokens());
        $this->assertNull($tokenUsage->getCompletionTokens());
        $this->assertNull($tokenUsage->getTotalTokens());
    }

    private function createResponseObject(): ResponseInterface|MockObject
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getHeaders')->willReturn([
            'x-ratelimit-remaining-tokens' => ['1000'],
        ]);

        return $response;
    }
}
