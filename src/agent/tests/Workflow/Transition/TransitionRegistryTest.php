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
use Symfony\AI\Agent\Workflow\Transition\TransitionRegistry;
use Symfony\AI\Agent\Workflow\WorkflowState;

final class TransitionRegistryTest extends TestCase
{
    public function testAddTransition(): void
    {
        $registry = new TransitionRegistry();
        $transition = new Transition('t1', 'start', 'processing');

        $registry->addTransition($transition);

        $this->assertSame($transition, $registry->getTransition('t1'));
    }

    public function testGetTransitionReturnsNullForUnknownTransition(): void
    {
        $registry = new TransitionRegistry();

        $this->assertNull($registry->getTransition('unknown'));
    }

    public function testGetAvailableTransitions(): void
    {
        $registry = new TransitionRegistry();

        $t1 = new Transition('t1', 'start', 'processing');
        $t2 = new Transition('t2', 'processing', 'completed');
        $t3 = new Transition('t3', 'start', 'cancelled');

        $registry->addTransition($t1);
        $registry->addTransition($t2);
        $registry->addTransition($t3);

        $state = new WorkflowState('id', 'start');
        $available = $registry->getAvailableTransitions($state);

        $this->assertCount(2, $available);
        $this->assertContains($t1, $available);
        $this->assertContains($t3, $available);
        $this->assertNotContains($t2, $available);
    }

    public function testGetAvailableTransitionsWithGuards(): void
    {
        $registry = new TransitionRegistry();

        $t1 = new Transition(
            't1',
            'start',
            'processing',
            guards: [static fn ($state) => true === $state->getContext()['ready']]
        );
        $t2 = new Transition('t2', 'start', 'cancelled');

        $registry->addTransition($t1);
        $registry->addTransition($t2);

        $state = new WorkflowState('id', 'start', ['ready' => false]);
        $available = $registry->getAvailableTransitions($state);

        $this->assertCount(1, $available);
        $this->assertContains($t2, $available);
    }

    public function testCanTransition(): void
    {
        $registry = new TransitionRegistry();
        $transition = new Transition('t1', 'start', 'processing');
        $registry->addTransition($transition);

        $state = new WorkflowState('id', 'start');

        $this->assertTrue($registry->canTransition('t1', $state));
        $this->assertFalse($registry->canTransition('unknown', $state));
    }
}
