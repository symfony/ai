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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Perplexity\Perplexity;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
#[CoversClass(Perplexity::class)]
#[Small]
final class PerplexityTest extends TestCase
{
    public function testItCreatesPerplexityWithDefaultSettings()
    {
        $perplexity = new Perplexity();

        $this->assertSame(Perplexity::SONAR, $perplexity->getName());
        $this->assertSame([], $perplexity->getOptions());
    }

    public function testItCreatesPerplexityWithCustomSettings()
    {
        $perplexity = new Perplexity(Perplexity::SONAR_PRO, ['temperature' => 0.5, 'max_tokens' => 1000]);

        $this->assertSame(Perplexity::SONAR_PRO, $perplexity->getName());
        $this->assertSame(['temperature' => 0.5, 'max_tokens' => 1000], $perplexity->getOptions());
    }
}
