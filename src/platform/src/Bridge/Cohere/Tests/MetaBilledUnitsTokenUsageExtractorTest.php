<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cohere\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Cohere\MetaBilledUnitsTokenUsageExtractor;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;

final class MetaBilledUnitsTokenUsageExtractorTest extends TestCase
{
    public function testItReturnsNullWithoutMetaData()
    {
        $extractor = new MetaBilledUnitsTokenUsageExtractor();

        $this->assertNull($extractor->extract(new InMemoryRawResult(['some' => 'data'])));
    }

    public function testItReturnsNullWithoutBilledUnitsFields()
    {
        $extractor = new MetaBilledUnitsTokenUsageExtractor();

        $result = new InMemoryRawResult([
            'meta' => [
                'billed_units' => [],
            ],
        ]);

        $this->assertNull($extractor->extract($result));
    }

    public function testItExtractsInputTokensFromEmbeddingsResponse()
    {
        $extractor = new MetaBilledUnitsTokenUsageExtractor();
        $result = new InMemoryRawResult([
            'meta' => [
                'billed_units' => [
                    'input_tokens' => 7,
                ],
            ],
        ]);

        $tokenUsage = $extractor->extract($result);

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(7, $tokenUsage->getPromptTokens());
    }

    public function testItExtractsSearchUnitsFromRerankingResponse()
    {
        $extractor = new MetaBilledUnitsTokenUsageExtractor();
        $result = new InMemoryRawResult([
            'meta' => [
                'billed_units' => [
                    'search_units' => 1,
                ],
            ],
        ]);

        $tokenUsage = $extractor->extract($result);

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(1, $tokenUsage->getPromptTokens());
    }

    public function testInputTokensTakePrecedenceOverSearchUnits()
    {
        $extractor = new MetaBilledUnitsTokenUsageExtractor();
        $result = new InMemoryRawResult([
            'meta' => [
                'billed_units' => [
                    'input_tokens' => 10,
                    'search_units' => 1,
                ],
            ],
        ]);

        $tokenUsage = $extractor->extract($result);

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(10, $tokenUsage->getPromptTokens());
    }
}
