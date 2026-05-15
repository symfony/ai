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
use Symfony\AI\Platform\Contract\JsonSchema\Factory;

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

    public function testHasAgentReturnsTrueForNonEmptyAgent()
    {
        $decision = new Decision('support', 'matched on keyword');

        $this->assertTrue($decision->hasAgent());
    }

    public function testHasAgentReturnsFalseForEmptyAgent()
    {
        $decision = new Decision('', 'no matching agent');

        $this->assertFalse($decision->hasAgent());
    }

    public function testJsonSchemaListsEveryPropertyAsRequired()
    {
        $schema = (new Factory())->buildProperties(Decision::class);

        $this->assertSame(['agentName', 'reasoning'], array_keys($schema['properties']));
        $this->assertSame(['agentName', 'reasoning'], $schema['required']);
    }
}
