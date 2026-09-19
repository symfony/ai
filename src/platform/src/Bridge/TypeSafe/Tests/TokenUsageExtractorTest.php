<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\TypeSafe\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\TypeSafe\TokenUsageExtractor;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;

final class TokenUsageExtractorTest extends TestCase
{
    public function testItExtractsTokenUsage()
    {
        $tokenUsage = (new TokenUsageExtractor())->extract(new InMemoryRawResult([
            'model' => 'jev-1.13.0',
            'answers' => [],
            'usage' => ['input_tokens' => 312, 'output_tokens' => 48],
        ]));

        $this->assertInstanceOf(TokenUsage::class, $tokenUsage);
        $this->assertSame(312, $tokenUsage->getPromptTokens());
        $this->assertSame(48, $tokenUsage->getCompletionTokens());
        $this->assertSame('jev-1.13.0', $tokenUsage->getModel());
    }

    public function testItReturnsNullWithoutUsage()
    {
        $this->assertNull((new TokenUsageExtractor())->extract(new InMemoryRawResult(['answers' => []])));
    }
}
