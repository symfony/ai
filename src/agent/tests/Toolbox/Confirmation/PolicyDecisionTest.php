<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Toolbox\Confirmation;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\Confirmation\PolicyDecision;

final class PolicyDecisionTest extends TestCase
{
    public function testEnumValues()
    {
        $this->assertSame('allow', PolicyDecision::Allow->value);
        $this->assertSame('deny', PolicyDecision::Deny->value);
        $this->assertSame('ask_user', PolicyDecision::AskUser->value);
    }

    public function testEnumCases()
    {
        $cases = PolicyDecision::cases();

        $this->assertCount(3, $cases);
        $this->assertContains(PolicyDecision::Allow, $cases);
        $this->assertContains(PolicyDecision::Deny, $cases);
        $this->assertContains(PolicyDecision::AskUser, $cases);
    }
}
