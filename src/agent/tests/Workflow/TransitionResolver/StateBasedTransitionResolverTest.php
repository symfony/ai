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
use Symfony\AI\Agent\Workflow\TransitionResolver\StateBasedTransitionResolver;
use Symfony\AI\Agent\Workflow\WorkflowState;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

final class StateBasedTransitionResolverTest extends TestCase
{
    public function testReturnsNullWhenNoTransitionsEnabled()
    {
        $resolver = new StateBasedTransitionResolver();
        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->method('getEnabledTransitions')->willReturn([]);

        $result = $resolver->resolve(
            new WorkflowState('id'),
            'final_place',
            $workflow,
            new \stdClass(),
        );

        $this->assertNull($result);
    }

    public function testReturnsSingleEnabledTransition()
    {
        $resolver = new StateBasedTransitionResolver();
        $transition = new Transition('to_review', 'draft', 'review');

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->method('getEnabledTransitions')->willReturn([$transition]);

        $result = $resolver->resolve(
            new WorkflowState('id'),
            'draft',
            $workflow,
            new \stdClass(),
        );

        $this->assertSame('to_review', $result);
    }

    public function testReturnsHintedTransitionFromState()
    {
        $resolver = new StateBasedTransitionResolver();
        $t1 = new Transition('approve', 'review', 'publish');
        $t2 = new Transition('reject', 'review', 'draft');

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->method('getEnabledTransitions')->willReturn([$t1, $t2]);

        $state = new WorkflowState('id');
        $state->withNextTransition('reject');

        $result = $resolver->resolve($state, 'review', $workflow, new \stdClass());

        $this->assertSame('reject', $result);
    }

    public function testThrowsWhenHintedTransitionIsNotEnabled()
    {
        $resolver = new StateBasedTransitionResolver();
        $t1 = new Transition('approve', 'review', 'publish');

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->method('getEnabledTransitions')->willReturn([$t1]);

        $state = new WorkflowState('id');
        $state->withNextTransition('nonexistent');

        $this->expectException(TransitionResolutionException::class);
        $this->expectExceptionMessage('Transition "nonexistent" is not enabled from place "review"');

        $resolver->resolve($state, 'review', $workflow, new \stdClass());
    }

    public function testThrowsWhenMultipleTransitionsAndNoHint()
    {
        $resolver = new StateBasedTransitionResolver();
        $t1 = new Transition('approve', 'review', 'publish');
        $t2 = new Transition('reject', 'review', 'draft');

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->method('getEnabledTransitions')->willReturn([$t1, $t2]);

        $this->expectException(TransitionResolutionException::class);
        $this->expectExceptionMessage('Multiple transitions are enabled from place "review"');

        $resolver->resolve(new WorkflowState('id'), 'review', $workflow, new \stdClass());
    }
}
