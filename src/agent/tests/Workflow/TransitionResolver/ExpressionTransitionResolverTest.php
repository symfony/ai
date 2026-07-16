<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Workflow\TransitionResolver;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Exception\TransitionResolutionException;
use Symfony\AI\Agent\Workflow\TransitionResolver\ExpressionTransitionResolver;
use Symfony\AI\Agent\Workflow\WorkflowState;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

final class ExpressionTransitionResolverTest extends TestCase
{
    public function testReturnsNullWhenNoTransitionsAreEnabled()
    {
        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->method('getEnabledTransitions')->willReturn([]);

        $resolver = new ExpressionTransitionResolver(['to_review' => 'true']);

        $result = $resolver->resolve(new WorkflowState('id'), 'draft', $workflow, new \stdClass());

        $this->assertNull($result);
    }

    public function testReturnsTransitionWhoseExpressionIsTruthy()
    {
        $approve = new Transition('approve', 'review', 'publish');
        $reject = new Transition('reject', 'review', 'draft');

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->method('getEnabledTransitions')->willReturn([$approve, $reject]);

        $resolver = new ExpressionTransitionResolver([
            'approve' => 'data["score"] >= 80',
            'reject' => 'data["score"] < 80',
        ]);

        $state = new WorkflowState('id', ['score' => 90]);

        $result = $resolver->resolve($state, 'review', $workflow, new \stdClass());

        $this->assertSame('approve', $result);
    }

    public function testPicksFirstMatchingExpressionWhenMultipleCouldMatch()
    {
        $t1 = new Transition('fast_track', 'review', 'publish');
        $t2 = new Transition('approve', 'review', 'publish');

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->method('getEnabledTransitions')->willReturn([$t1, $t2]);

        // Both expressions evaluate truthy; first one in the map wins
        $resolver = new ExpressionTransitionResolver([
            'fast_track' => 'true',
            'approve' => 'true',
        ]);

        $result = $resolver->resolve(new WorkflowState('id'), 'review', $workflow, new \stdClass());

        $this->assertSame('fast_track', $result);
    }

    public function testSkipsExpressionForTransitionThatIsNotEnabled()
    {
        $approve = new Transition('approve', 'review', 'publish');

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->method('getEnabledTransitions')->willReturn([$approve]);

        // 'reject' is in the expressions map but NOT enabled → must be ignored
        $resolver = new ExpressionTransitionResolver([
            'reject' => 'true',
            'approve' => 'true',
        ]);

        $result = $resolver->resolve(new WorkflowState('id'), 'review', $workflow, new \stdClass());

        $this->assertSame('approve', $result);
    }

    public function testFallsBackToStateBasedResolutionWhenNoExpressionMatches()
    {
        // Single enabled transition, no expression matches → state-based picks it
        $to_final = new Transition('to_final', 'processing', 'done');

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->method('getEnabledTransitions')->willReturn([$to_final]);

        $resolver = new ExpressionTransitionResolver([
            'to_final' => 'false', // expression does not match
        ]);

        $result = $resolver->resolve(new WorkflowState('id'), 'processing', $workflow, new \stdClass());

        $this->assertSame('to_final', $result);
    }

    public function testFallsBackToStateBasedResolutionWhenExpressionsMapIsEmpty()
    {
        $only = new Transition('proceed', 'draft', 'review');

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->method('getEnabledTransitions')->willReturn([$only]);

        $resolver = new ExpressionTransitionResolver([]);

        $result = $resolver->resolve(new WorkflowState('id'), 'draft', $workflow, new \stdClass());

        $this->assertSame('proceed', $result);
    }

    public function testExpressionCanReadPlaceVariable()
    {
        $t1 = new Transition('express_review', 'draft', 'publish');
        $t2 = new Transition('normal_review', 'draft', 'review');

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->method('getEnabledTransitions')->willReturn([$t1, $t2]);

        $resolver = new ExpressionTransitionResolver([
            'express_review' => 'place === "draft"',
        ]);

        $result = $resolver->resolve(new WorkflowState('id'), 'draft', $workflow, new \stdClass());

        $this->assertSame('express_review', $result);
    }

    public function testExpressionCanReadTransitionsVariable()
    {
        $t1 = new Transition('approve', 'review', 'publish');
        $t2 = new Transition('reject', 'review', 'draft');

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->method('getEnabledTransitions')->willReturn([$t1, $t2]);

        $resolver = new ExpressionTransitionResolver([
            'reject' => '"approve" in transitions and "reject" in transitions',
        ]);

        $result = $resolver->resolve(new WorkflowState('id'), 'review', $workflow, new \stdClass());

        $this->assertSame('reject', $result);
    }

    public function testWrapsEvaluationErrorInTransitionResolutionException()
    {
        $t1 = new Transition('go', 'a', 'b');

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->method('getEnabledTransitions')->willReturn([$t1]);

        // deliberately invalid expression that will throw during evaluation
        $resolver = new ExpressionTransitionResolver([
            'go' => 'undefinedFunction()',
        ]);

        $this->expectException(TransitionResolutionException::class);

        $resolver->resolve(new WorkflowState('id'), 'a', $workflow, new \stdClass());
    }

    public function testFallsBackAndThrowsWhenMultipleTransitionsEnabledAndNoneMatch()
    {
        $t1 = new Transition('approve', 'review', 'publish');
        $t2 = new Transition('reject', 'review', 'draft');

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->method('getEnabledTransitions')->willReturn([$t1, $t2]);

        // No expression matches → fallback to state-based → multiple transitions → throws
        $resolver = new ExpressionTransitionResolver([
            'approve' => 'false',
            'reject' => 'false',
        ]);

        $this->expectException(TransitionResolutionException::class);

        $resolver->resolve(new WorkflowState('id'), 'review', $workflow, new \stdClass());
    }

    public function testStateHintIsUsedByFallbackWhenNoExpressionMatches()
    {
        $t1 = new Transition('approve', 'review', 'publish');
        $t2 = new Transition('reject', 'review', 'draft');

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->method('getEnabledTransitions')->willReturn([$t1, $t2]);

        $resolver = new ExpressionTransitionResolver([
            'approve' => 'false',
            'reject' => 'false',
        ]);

        $state = (new WorkflowState('id'))->withNextTransition('reject');

        $result = $resolver->resolve($state, 'review', $workflow, new \stdClass());

        $this->assertSame('reject', $result);
    }
}
