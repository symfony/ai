<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\MultiAgent\Handoff;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\MultiAgent\Handoff\Decision;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
class DecisionTest extends TestCase
{
    public function testConstructorWithAgentName()
    {
        $decision = new Decision('technical', 'This is a technical question');

        $this->assertSame('technical', $decision->getAgentName());
        $this->assertSame('This is a technical question', $decision->getReasoning());
        $this->assertTrue($decision->hasAgent());
    }

    public function testConstructorWithEmptyAgentName()
    {
        $decision = new Decision('', 'No specific agent needed');

        $this->assertSame('', $decision->getAgentName());
        $this->assertSame('No specific agent needed', $decision->getReasoning());
        $this->assertFalse($decision->hasAgent());
    }

    public function testConstructorWithDefaultReasoning()
    {
        $decision = new Decision('general');

        $this->assertSame('general', $decision->getAgentName());
        $this->assertSame('No reasoning provided', $decision->getReasoning());
        $this->assertTrue($decision->hasAgent());
    }

    public function testConstructorWithEmptyAgentAndDefaultReasoning()
    {
        $decision = new Decision('');

        $this->assertSame('', $decision->getAgentName());
        $this->assertSame('No reasoning provided', $decision->getReasoning());
        $this->assertFalse($decision->hasAgent());
    }

    public function testHasAgentReturnsTrueForNonEmptyAgent()
    {
        $decision = new Decision('support');

        $this->assertTrue($decision->hasAgent());
    }

    public function testHasAgentReturnsFalseForEmptyAgent()
    {
        $decision = new Decision('');

        $this->assertFalse($decision->hasAgent());
    }
}
