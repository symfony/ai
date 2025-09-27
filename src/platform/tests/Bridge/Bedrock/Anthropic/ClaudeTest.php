<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bridge\Bedrock\Anthropic;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class ClaudeTest extends TestCase
{
    public function testItCreatesClaudeWithDefaultSettings()
    {
        $claude = new Claude(Claude::SONNET_35);

        $this->assertSame(Claude::SONNET_35, $claude->getName());
        $this->assertSame(['max_tokens' => 1000], $claude->getOptions());
    }

    public function testItCreatesClaudeWithCustomSettingsIncludingMaxTokens()
    {
        $claude = new Claude(Claude::SONNET_35, options: ['temperature' => 0.5, 'max_tokens' => 2000]);

        $this->assertSame(Claude::SONNET_35, $claude->getName());
        $this->assertSame(['temperature' => 0.5, 'max_tokens' => 2000], $claude->getOptions());
    }

    public function testItCreatesClaudeWithCustomSettingsWithoutMaxTokens()
    {
        $claude = new Claude(Claude::SONNET_35, options: ['temperature' => 0.5]);

        $this->assertSame(Claude::SONNET_35, $claude->getName());
        $this->assertSame(['temperature' => 0.5, 'max_tokens' => 1000], $claude->getOptions());
    }
}
