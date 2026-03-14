<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Mistral\Embeddings;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Mistral\Embeddings\TokenUsageExtractor;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class TokenUsageExtractorTest extends TestCase
{
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
                'prompt_tokens' => 10,
                'total_tokens' => 10,
            ],
        ], object: $this->createResponseObject());

        $tokenUsage = $extractor->extract($result);

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(1000, $tokenUsage->getRemainingTokensMinute());
        $this->assertSame(1000000, $tokenUsage->getRemainingTokensMonth());
        $this->assertSame(10, $tokenUsage->getPromptTokens());
        $this->assertNull($tokenUsage->getCompletionTokens());
        $this->assertSame(10, $tokenUsage->getTotalTokens());
    }

    public function testItHandlesMissingUsageFields()
    {
        $extractor = new TokenUsageExtractor();
        $result = new InMemoryRawResult([
            'usage' => [
                'prompt_tokens' => 10,
            ],
        ], object: $this->createResponseObject());

        $tokenUsage = $extractor->extract($result);

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(1000, $tokenUsage->getRemainingTokensMinute());
        $this->assertSame(1000000, $tokenUsage->getRemainingTokensMonth());
        $this->assertSame(10, $tokenUsage->getPromptTokens());
        $this->assertNull($tokenUsage->getCompletionTokens());
        $this->assertNull($tokenUsage->getTotalTokens());
    }

    private function createResponseObject(): ResponseInterface|MockObject
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getHeaders')->willReturn([
            'x-ratelimit-limit-tokens-minute' => ['1000'],
            'x-ratelimit-limit-tokens-month' => ['1000000'],
        ]);

        return $response;
    }
}
