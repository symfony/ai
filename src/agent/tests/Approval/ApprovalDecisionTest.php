<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Approval;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Approval\ApprovalDecision;
use Symfony\AI\Agent\Approval\ApprovalDecisionType;

final class ApprovalDecisionTest extends TestCase
{
    public function testApproveFactory()
    {
        $decision = ApprovalDecision::approve('LGTM');

        $this->assertSame(ApprovalDecisionType::Approved, $decision->getType());
        $this->assertTrue($decision->isApproved());
        $this->assertFalse($decision->isRejected());
        $this->assertFalse($decision->isModified());
        $this->assertSame('LGTM', $decision->getFeedback());
        $this->assertNull($decision->getModifiedArguments());
    }

    public function testRejectFactory()
    {
        $decision = ApprovalDecision::reject('Amount too large');

        $this->assertSame(ApprovalDecisionType::Rejected, $decision->getType());
        $this->assertFalse($decision->isApproved());
        $this->assertTrue($decision->isRejected());
        $this->assertFalse($decision->isModified());
        $this->assertSame('Amount too large', $decision->getFeedback());
        $this->assertNull($decision->getModifiedArguments());
    }

    public function testModifyFactory()
    {
        $decision = ApprovalDecision::modify(['amount' => 50], 'Capped at 50');

        $this->assertSame(ApprovalDecisionType::Modified, $decision->getType());
        $this->assertFalse($decision->isApproved());
        $this->assertFalse($decision->isRejected());
        $this->assertTrue($decision->isModified());
        $this->assertSame('Capped at 50', $decision->getFeedback());
        $this->assertSame(['amount' => 50], $decision->getModifiedArguments());
    }
}
