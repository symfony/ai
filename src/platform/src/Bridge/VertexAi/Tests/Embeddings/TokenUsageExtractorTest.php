<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\VertexAi\Tests\Embeddings;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\VertexAi\Embeddings\TokenUsageExtractor;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;

final class TokenUsageExtractorTest extends TestCase
{
    public function testItReturnsNullWithoutPredictions()
    {
        $extractor = new TokenUsageExtractor();

        $this->assertNull($extractor->extract(new InMemoryRawResult(['some' => 'data'])));
    }

    public function testItExtractsTokenUsageFromSinglePrediction()
    {
        $extractor = new TokenUsageExtractor();
        $result = new InMemoryRawResult([
            'predictions' => [
                [
                    'embeddings' => [
                        'values' => [0.1, 0.2, 0.3],
                        'statistics' => [
                            'token_count' => 15,
                            'truncated' => false,
                        ],
                    ],
                ],
            ],
        ]);

        $tokenUsage = $extractor->extract($result);

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(15, $tokenUsage->getPromptTokens());
        $this->assertSame(15, $tokenUsage->getTotalTokens());
    }

    public function testItSumsTokenUsageFromMultiplePredictions()
    {
        $extractor = new TokenUsageExtractor();
        $result = new InMemoryRawResult([
            'predictions' => [
                [
                    'embeddings' => [
                        'values' => [0.1, 0.2],
                        'statistics' => [
                            'token_count' => 10,
                            'truncated' => false,
                        ],
                    ],
                ],
                [
                    'embeddings' => [
                        'values' => [0.3, 0.4],
                        'statistics' => [
                            'token_count' => 8,
                            'truncated' => false,
                        ],
                    ],
                ],
                [
                    'embeddings' => [
                        'values' => [0.5, 0.6],
                        'statistics' => [
                            'token_count' => 12,
                            'truncated' => false,
                        ],
                    ],
                ],
            ],
        ]);

        $tokenUsage = $extractor->extract($result);

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(30, $tokenUsage->getPromptTokens());
        $this->assertSame(30, $tokenUsage->getTotalTokens());
    }

    public function testItReturnsNullWhenNoStatisticsAvailable()
    {
        $extractor = new TokenUsageExtractor();
        $result = new InMemoryRawResult([
            'predictions' => [
                [
                    'embeddings' => [
                        'values' => [0.1, 0.2, 0.3],
                    ],
                ],
            ],
        ]);

        $this->assertNull($extractor->extract($result));
    }
}
