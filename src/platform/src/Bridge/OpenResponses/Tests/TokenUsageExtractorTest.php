<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenResponses\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenResponses\TokenUsageExtractor;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;

/**
 * @author Illia Vasylevskyi <ineersa@gmail.com>
 */
final class TokenUsageExtractorTest extends TestCase
{
    #[DataProvider('provideCacheWriteTokens')]
    public function testExtractsCacheWriteTokens(?int $cacheWriteTokens)
    {
        $inputTokensDetails = ['cached_tokens' => 3];
        if (null !== $cacheWriteTokens) {
            $inputTokensDetails['cache_write_tokens'] = $cacheWriteTokens;
        }

        $result = new InMemoryRawResult([
            'model' => 'gpt-5.6-sol',
            'usage' => [
                'input_tokens' => 11,
                'input_tokens_details' => $inputTokensDetails,
                'output_tokens' => 7,
                'output_tokens_details' => ['reasoning_tokens' => 2],
                'total_tokens' => 18,
            ],
        ]);

        $tokenUsage = (new TokenUsageExtractor())->extract($result);

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame($cacheWriteTokens, $tokenUsage->getCacheCreationTokens());
        $this->assertSame(11, $tokenUsage->getPromptTokens());
        $this->assertSame(3, $tokenUsage->getCachedTokens());
        $this->assertSame(7, $tokenUsage->getCompletionTokens());
        $this->assertSame(2, $tokenUsage->getThinkingTokens());
        $this->assertSame(18, $tokenUsage->getTotalTokens());
        $this->assertSame('gpt-5.6-sol', $tokenUsage->getModel());
    }

    /**
     * @return iterable<string, array{int|null}>
     */
    public static function provideCacheWriteTokens(): iterable
    {
        yield 'positive' => [4];
        yield 'zero' => [0];
        yield 'missing' => [null];
    }
}
