<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Voyage\Tests\Reranker;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Voyage\Reranker\TokenUsageExtractor;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class TokenUsageExtractorTest extends TestCase
{
    public function testItExtractsTotalTokens()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'object' => 'list',
            'data' => [['index' => 0, 'relevance_score' => 0.95]],
            'usage' => ['total_tokens' => 18],
        ]);

        $extractor = new TokenUsageExtractor();
        $usage = $extractor->extract(new RawHttpResult($response));

        $this->assertInstanceOf(TokenUsageInterface::class, $usage);
        $this->assertSame(18, $usage->getTotalTokens());
    }

    public function testItReturnsNullWithoutUsage()
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn(['object' => 'list', 'data' => []]);

        $extractor = new TokenUsageExtractor();

        $this->assertNull($extractor->extract(new RawHttpResult($response)));
    }
}
