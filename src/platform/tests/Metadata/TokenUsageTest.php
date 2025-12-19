<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Metadata;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;

class TokenUsageTest extends TestCase
{
    public function testDefaultValuesAreNull()
    {
        $usage = new TokenUsage();

        $this->assertInstanceOf(TokenUsageInterface::class, $usage);

        $this->assertNull($usage->getPromptTokens());
        $this->assertNull($usage->getCompletionTokens());
        $this->assertNull($usage->getThinkingTokens());
        $this->assertNull($usage->getToolTokens());
        $this->assertNull($usage->getCachedTokens());
        $this->assertNull($usage->getRemainingTokens());
        $this->assertNull($usage->getRemainingTokensMinute());
        $this->assertNull($usage->getRemainingTokensMonth());
        $this->assertNull($usage->getTotalTokens());
    }

    public function testValuesAreSetCorrectly()
    {
        $usage = new TokenUsage(1, 2, 3, 4, 5, 6, 7, 8, 9);

        $this->assertSame(1, $usage->getPromptTokens());
        $this->assertSame(2, $usage->getCompletionTokens());
        $this->assertSame(3, $usage->getThinkingTokens());
        $this->assertSame(4, $usage->getToolTokens());
        $this->assertSame(5, $usage->getCachedTokens());
        $this->assertSame(6, $usage->getRemainingTokens());
        $this->assertSame(7, $usage->getRemainingTokensMinute());
        $this->assertSame(8, $usage->getRemainingTokensMonth());
        $this->assertSame(9, $usage->getTotalTokens());
    }
}
