<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Workflow;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Workflow\ExpressionGuard;
use Symfony\AI\Agent\Workflow\WorkflowState;

final class ExpressionGuardTest extends TestCase
{
    public function testSupportsEveryPlaceWhenNoPlacesGiven()
    {
        $guard = new ExpressionGuard('true');

        $this->assertTrue($guard->supports('draft'));
        $this->assertTrue($guard->supports('review'));
        $this->assertTrue($guard->supports('publish'));
    }

    public function testSupportsOnlyTheConfiguredPlaces()
    {
        $guard = new ExpressionGuard('true', ['review', 'publish']);

        $this->assertTrue($guard->supports('review'));
        $this->assertTrue($guard->supports('publish'));
        $this->assertFalse($guard->supports('draft'));
    }

    public function testAllowsWhenExpressionEvaluatesToTrue()
    {
        $guard = new ExpressionGuard('true');
        $state = new WorkflowState('run-1');

        $this->assertTrue($guard->allows($state, 'draft'));
    }

    public function testDeniesWhenExpressionEvaluatesToFalse()
    {
        $guard = new ExpressionGuard('false');
        $state = new WorkflowState('run-2');

        $this->assertFalse($guard->allows($state, 'draft'));
    }

    public function testExpressionCanReadStateDataViaDataVariable()
    {
        $guard = new ExpressionGuard('data["score"] >= 80');
        $state = new WorkflowState('run-3', ['score' => 90]);

        $this->assertTrue($guard->allows($state, 'review'));
    }

    public function testExpressionDeniesWhenStateDataDoesNotMatch()
    {
        $guard = new ExpressionGuard('data["score"] >= 80');
        $state = new WorkflowState('run-4', ['score' => 50]);

        $this->assertFalse($guard->allows($state, 'review'));
    }

    public function testExpressionReceivesPlaceVariable()
    {
        $guard = new ExpressionGuard('place === "publish"');
        $state = new WorkflowState('run-5');

        $this->assertTrue($guard->allows($state, 'publish'));
        $this->assertFalse($guard->allows($state, 'draft'));
    }

    public function testExpressionCanAccessStateObjectDirectly()
    {
        $guard = new ExpressionGuard('state.has("approved")');
        $approvedState = new WorkflowState('run-6', ['approved' => true]);
        $pendingState = new WorkflowState('run-7');

        $this->assertTrue($guard->allows($approvedState, 'publish'));
        $this->assertFalse($guard->allows($pendingState, 'publish'));
    }

    public function testGuardAppliesToSinglePlaceWhenListHasOneEntry()
    {
        $guard = new ExpressionGuard('true', ['only-place']);

        $this->assertTrue($guard->supports('only-place'));
        $this->assertFalse($guard->supports('other-place'));
    }
}
