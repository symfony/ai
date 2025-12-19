<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\VertexAi;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\VertexAi\Gemini\TokenUsageExtractor;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;

final class TokenUsageExtractorTest extends TestCase
{
    public function testItDoesNothingWithoutUsageData()
    {
        $extractor = new TokenUsageExtractor();
        $result = new InMemoryRawResult(['other' => 'data']);

        $this->assertNull($extractor->extract($result));
    }

    public function testItExtractsTokenUsage()
    {
        $extractor = new TokenUsageExtractor();
        $result = new InMemoryRawResult([
            'usageMetadata' => [
                'promptTokenCount' => 10,
                'candidatesTokenCount' => 20,
                'thoughtsTokenCount' => 20,
                'totalTokenCount' => 50,
            ],
        ]);

        $tokenUsage = $extractor->extract($result);

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(10, $tokenUsage->getPromptTokens());
        $this->assertSame(20, $tokenUsage->getCompletionTokens());
        $this->assertSame(20, $tokenUsage->getThinkingTokens());
        $this->assertSame(50, $tokenUsage->getTotalTokens());
    }

    public function testItHandlesMissingUsageFields()
    {
        $extractor = new TokenUsageExtractor();
        $result = new InMemoryRawResult([
            'usageMetadata' => [
                'promptTokenCount' => 10,
            ],
        ]);

        $tokenUsage = $extractor->extract($result);

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(10, $tokenUsage->getPromptTokens());
        $this->assertNull($tokenUsage->getCompletionTokens());
        $this->assertNull($tokenUsage->getThinkingTokens());
        $this->assertNull($tokenUsage->getTotalTokens());
    }

    public function testItHandlesStreamResults()
    {
        $extractor = new TokenUsageExtractor();
        $result = new InMemoryRawResult(dataStream: [
            ['content' => 'chunk1'],
            ['content' => 'chunk2', 'usageMetadata' => [
                'promptTokenCount' => 15,
                'candidatesTokenCount' => 25,
                'totalTokenCount' => 40,
            ]],
        ]);

        $tokenUsage = $extractor->extract($result, ['stream' => true]);

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(15, $tokenUsage->getPromptTokens());
        $this->assertSame(25, $tokenUsage->getCompletionTokens());
        $this->assertNull($tokenUsage->getThinkingTokens());
        $this->assertSame(40, $tokenUsage->getTotalTokens());
    }
}
