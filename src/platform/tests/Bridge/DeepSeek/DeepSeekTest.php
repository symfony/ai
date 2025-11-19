<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\DeepSeek;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\DeepSeek\DeepSeek;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class DeepSeekTest extends TestCase
{
    public function testItCreatesDeepSeekWithDefaultSettings()
    {
        $deepSeek = new DeepSeek('deepseek-chat');

        $this->assertSame('deepseek-chat', $deepSeek->getName());
        $this->assertSame([], $deepSeek->getOptions());
    }

    public function testItCreatesDeepSeekWithCustomSettings()
    {
        $deepSeek = new DeepSeek('deepseek-chat', [], ['temperature' => 0.5]);

        $this->assertSame('deepseek-chat', $deepSeek->getName());
        $this->assertSame(['temperature' => 0.5], $deepSeek->getOptions());
    }

    public function testItCreatesDeepSeekReasoner()
    {
        $deepSeek = new DeepSeek('deepseek-reasoner');

        $this->assertSame('deepseek-reasoner', $deepSeek->getName());
        $this->assertSame([], $deepSeek->getOptions());
    }
}
