<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Acp\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Acp\TokenUsageExtractor;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsage;

final class TokenUsageExtractorTest extends TestCase
{
    public function testItReturnsNullWhenUsageIsMissing(): void
    {
        $extractor = new TokenUsageExtractor();
        $rawResult = new FakeRawResult([]);

        $this->assertNull($extractor->extract($rawResult));
    }

    public function testItReturnsNullForStreamOption(): void
    {
        $extractor = new TokenUsageExtractor();
        $rawResult = new FakeRawResult([
            'usage' => [
                'inputTokens' => 10,
                'outputTokens' => 5,
                'thoughtTokens' => 2,
                'totalTokens' => 17,
            ],
        ]);

        $this->assertNull($extractor->extract($rawResult, ['stream' => true]));
    }

    public function testItExtractsTokenUsage(): void
    {
        $extractor = new TokenUsageExtractor();
        $rawResult = new FakeRawResult([
            'usage' => [
                'inputTokens' => 12901,
                'outputTokens' => 53,
                'thoughtTokens' => 78,
                'totalTokens' => 13032,
            ],
        ]);

        $tokenUsage = $extractor->extract($rawResult);

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(12901, $tokenUsage->getPromptTokens());
        $this->assertSame(53, $tokenUsage->getCompletionTokens());
        $this->assertSame(78, $tokenUsage->getThinkingTokens());
        $this->assertSame(13032, $tokenUsage->getTotalTokens());
    }
}

final class FakeRawResult implements RawResultInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly array $data,
    ) {
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getDataStream(): iterable
    {
        return [];
    }

    public function getObject(): object
    {
        return new \stdClass();
    }
}
