<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Workflow\Transition;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Workflow\Transition\Transition;
use Symfony\AI\Agent\Workflow\WorkflowState;

final class TransitionTest extends TestCase
{
    public function testConstruction(): void
    {
        $transition = new Transition(
            name: 'to_processing',
            from: 'start',
            to: 'processing'
        );

        $this->assertSame('to_processing', $transition->getName());
        $this->assertSame('start', $transition->getFrom());
        $this->assertSame('processing', $transition->getTo());
    }

    public function testCanTransitionReturnsTrueWhenCurrentStepMatches(): void
    {
        $transition = new Transition('t1', 'start', 'processing');
        $state = new WorkflowState('id', 'start');

        $this->assertTrue($transition->canTransition($state));
    }

    public function testCanTransitionReturnsFalseWhenCurrentStepDoesNotMatch(): void
    {
        $transition = new Transition('t1', 'start', 'processing');
        $state = new WorkflowState('id', 'other');

        $this->assertFalse($transition->canTransition($state));
    }

    public function testCanTransitionWithGuardReturnsTrue(): void
    {
        $guard = static fn ($state) => true === $state->getContext()['ready'];
        $transition = new Transition('t1', 'start', 'processing', guards: [$guard]);

        $state = new WorkflowState('id', 'start', ['ready' => true]);

        $this->assertTrue($transition->canTransition($state));
    }

    public function testCanTransitionWithGuardReturnsFalse(): void
    {
        $guard = static fn ($state) => true === $state->getContext()['ready'];
        $transition = new Transition('t1', 'start', 'processing', guards: [$guard]);

        $state = new WorkflowState('id', 'start', ['ready' => false]);

        $this->assertFalse($transition->canTransition($state));
    }

    public function testCanTransitionWithMultipleGuards(): void
    {
        $guard1 = static fn ($state) => true === $state->getContext()['check1'];
        $guard2 = static fn ($state) => true === $state->getContext()['check2'];

        $transition = new Transition('t1', 'start', 'processing', guards: [$guard1, $guard2]);

        $state = new WorkflowState('id', 'start', ['check1' => true, 'check2' => true]);
        $this->assertTrue($transition->canTransition($state));

        $state = new WorkflowState('id', 'start', ['check1' => true, 'check2' => false]);
        $this->assertFalse($transition->canTransition($state));
    }

    public function testBeforeTransitionCallback(): void
    {
        $called = false;
        $beforeCallback = static function ($state) use (&$called) {
            $called = true;
        };

        $transition = new Transition('t1', 'start', 'processing', beforeCallback: $beforeCallback);
        $state = new WorkflowState('id', 'start');

        $transition->beforeTransition($state);

        $this->assertTrue($called);
    }

    public function testAfterTransitionCallback(): void
    {
        $called = false;
        $afterCallback = static function ($state) use (&$called) {
            $called = true;
        };

        $transition = new Transition('t1', 'start', 'processing', afterCallback: $afterCallback);
        $state = new WorkflowState('id', 'start');

        $transition->afterTransition($state);

        $this->assertTrue($called);
        $this->assertSame('processing', $state->getCurrentStep());
    }

    public function testAfterTransitionUpdatesCurrentStep(): void
    {
        $transition = new Transition('t1', 'start', 'processing');
        $state = new WorkflowState('id', 'start');

        $transition->afterTransition($state);

        $this->assertSame('processing', $state->getCurrentStep());
    }
}
