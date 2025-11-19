<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Perplexity;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Perplexity\Perplexity;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class PerplexityTest extends TestCase
{
    public function testItCreatesPerplexityWithDefaultSettings()
    {
        $perplexity = new Perplexity('sonar');

        $this->assertSame('sonar', $perplexity->getName());
        $this->assertSame([], $perplexity->getOptions());
    }

    public function testItCreatesPerplexityWithCustomSettings()
    {
        $perplexity = new Perplexity('sonar-pro', options: ['temperature' => 0.5, 'max_tokens' => 1000]);

        $this->assertSame('sonar-pro', $perplexity->getName());
        $this->assertSame(['temperature' => 0.5, 'max_tokens' => 1000], $perplexity->getOptions());
    }
}
