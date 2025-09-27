<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Anthropic;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ClaudeTest extends TestCase
{
    public function testItCreatesClaudeWithDefaultSettings()
    {
        $claude = new Claude('claude-3-5-sonnet-latest');

        $this->assertSame('claude-3-5-sonnet-latest', $claude->getName());
        $this->assertSame(['max_tokens' => 1000], $claude->getOptions());
    }

    public function testItCreatesClaudeWithCustomSettingsIncludingMaxTokens()
    {
        $claude = new Claude('claude-3-5-sonnet-latest', [], ['temperature' => 0.5, 'max_tokens' => 2000]);

        $this->assertSame('claude-3-5-sonnet-latest', $claude->getName());
        $this->assertSame(['temperature' => 0.5, 'max_tokens' => 2000], $claude->getOptions());
    }

    public function testItCreatesClaudeWithCustomSettingsWithoutMaxTokens()
    {
        $claude = new Claude('claude-3-5-sonnet-latest', [], ['temperature' => 0.5]);

        $this->assertSame('claude-3-5-sonnet-latest', $claude->getName());
        $this->assertSame(['temperature' => 0.5, 'max_tokens' => 1000], $claude->getOptions());
    }
}
