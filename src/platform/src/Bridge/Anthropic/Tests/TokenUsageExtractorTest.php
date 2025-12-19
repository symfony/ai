<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bridge\Anthropic;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\TokenUsageExtractor;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;

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
                'server_tool_use' => [
                    'web_search_requests' => 30,
                ],
                'cache_creation_input_tokens' => 40,
                'cache_read_input_tokens' => 50,
            ],
        ]);

        $tokenUsage = $extractor->extract($result);

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(10, $tokenUsage->getPromptTokens());
        $this->assertSame(30, $tokenUsage->getToolTokens());
        $this->assertSame(20, $tokenUsage->getCompletionTokens());
        $this->assertNull($tokenUsage->getRemainingTokens());
        $this->assertNull($tokenUsage->getThinkingTokens());
        $this->assertSame(90, $tokenUsage->getCachedTokens());
        $this->assertNull($tokenUsage->getTotalTokens());
    }

    public function testItHandlesMissingUsageFields()
    {
        $extractor = new TokenUsageExtractor();
        $result = new InMemoryRawResult([
            'usage' => [
                // Missing some fields
                'input_tokens' => 10,
            ],
        ]);

        $tokenUsage = $extractor->extract($result);

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(10, $tokenUsage->getPromptTokens());
        $this->assertNull($tokenUsage->getRemainingTokens());
        $this->assertNull($tokenUsage->getCompletionTokens());
        $this->assertNull($tokenUsage->getTotalTokens());
    }
}
