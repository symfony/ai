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
use Symfony\AI\Agent\Exception\WorkflowMergeConflictException;
use Symfony\AI\Agent\Workflow\MergePolicy;
use Symfony\AI\Agent\Workflow\WorkflowState;

final class WorkflowStateTest extends TestCase
{
    public function testConstructorDefaults()
    {
        $state = new WorkflowState('test-id');

        $this->assertSame('test-id', $state->getId());
        $this->assertSame([], $state->all());
        $this->assertSame([], $state->getCompletedPlaces());
        $this->assertNull($state->getCurrentPlace());
        $this->assertNull($state->getNextTransition());
    }

    public function testConstructorWithData()
    {
        $state = new WorkflowState('id', ['key' => 'value'], ['place1'], 'place2', 'to_place3');

        $this->assertSame(['key' => 'value'], $state->all());
        $this->assertSame(['place1'], $state->getCompletedPlaces());
        $this->assertSame('place2', $state->getCurrentPlace());
        $this->assertSame('to_place3', $state->getNextTransition());
    }

    public function testHasAndGet()
    {
        $state = new WorkflowState('id', ['foo' => 'bar']);

        $this->assertTrue($state->has('foo'));
        $this->assertFalse($state->has('baz'));
        $this->assertSame('bar', $state->get('foo'));
        $this->assertNull($state->get('baz'));
        $this->assertSame('default', $state->get('baz', 'default'));
    }

    public function testSetReturnsNewInstanceAndLeavesOriginalUntouched()
    {
        $state = new WorkflowState('id');
        $result = $state->set('key', 'value');

        $this->assertNotSame($state, $result);
        $this->assertFalse($state->has('key'));
        $this->assertSame('value', $result->get('key'));
    }

    public function testUnsetReturnsNewInstance()
    {
        $state = new WorkflowState('id', ['key' => 'value']);
        $result = $state->unset('key');

        $this->assertNotSame($state, $result);
        $this->assertTrue($state->has('key'));
        $this->assertFalse($result->has('key'));
    }

    public function testMergeReturnsNewInstance()
    {
        $state = new WorkflowState('id', ['a' => 1]);
        $result = $state->merge(['b' => 2, 'c' => 3]);

        $this->assertNotSame($state, $result);
        $this->assertSame(['a' => 1], $state->all());
        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 3], $result->all());
    }

    public function testMergeOverwritesExistingKeys()
    {
        $state = new WorkflowState('id', ['a' => 1]);
        $result = $state->merge(['a' => 2]);

        $this->assertSame(2, $result->get('a'));
    }

    public function testWithCurrentPlaceReturnsNewInstance()
    {
        $state = new WorkflowState('id');
        $result = $state->withCurrentPlace('draft');

        $this->assertNotSame($state, $result);
        $this->assertNull($state->getCurrentPlace());
        $this->assertSame('draft', $result->getCurrentPlace());
    }

    public function testMarkCompletedAppendsPlaceAndClearsCurrentPlace()
    {
        $state = (new WorkflowState('id'))->withCurrentPlace('draft');
        $result = $state->markCompleted('draft');

        $this->assertNotSame($state, $result);
        $this->assertSame(['draft'], $result->getCompletedPlaces());
        $this->assertNull($result->getCurrentPlace());
        $this->assertSame(['draft', 'review'], $result->markCompleted('review')->getCompletedPlaces());
    }

    public function testNextTransition()
    {
        $state = new WorkflowState('id');
        $this->assertNull($state->getNextTransition());

        $withTransition = $state->withNextTransition('approve');
        $this->assertNotSame($state, $withTransition);
        $this->assertNull($state->getNextTransition());
        $this->assertSame('approve', $withTransition->getNextTransition());

        $cleared = $withTransition->clearNextTransition();
        $this->assertNotSame($withTransition, $cleared);
        $this->assertNull($cleared->getNextTransition());
    }

    public function testCount()
    {
        $state = new WorkflowState('id', ['a' => 1, 'b' => 2]);

        $this->assertCount(2, $state);
    }

    public function testSerialization()
    {
        $state = new WorkflowState('id', ['key' => 'value'], ['place1'], 'place2', 'to_place3');

        $deserialized = unserialize(serialize($state));

        $this->assertInstanceOf(WorkflowState::class, $deserialized);
        $this->assertSame('id', $deserialized->getId());
        $this->assertSame(['key' => 'value'], $deserialized->all());
        $this->assertSame(['place1'], $deserialized->getCompletedPlaces());
        $this->assertSame('place2', $deserialized->getCurrentPlace());
        $this->assertSame('to_place3', $deserialized->getNextTransition());
    }

    public function testMergeBranchesNonConflictingWritesAllAppear()
    {
        $base = new WorkflowState('merge-1', ['shared' => 'base']);

        $branchA = (new WorkflowState('merge-1', ['shared' => 'base']))->set('only-a', 'from-a');
        $branchB = (new WorkflowState('merge-1', ['shared' => 'base']))->set('only-b', 'from-b');

        $merged = WorkflowState::mergeBranches($base, ['place-a' => $branchA, 'place-b' => $branchB]);

        $this->assertSame('from-a', $merged->get('only-a'));
        $this->assertSame('from-b', $merged->get('only-b'));
        $this->assertSame('base', $merged->get('shared'));
    }

    public function testMergeBranchesCompletedPlacesUnion()
    {
        $base = new WorkflowState('merge-2', [], ['pre-existing']);

        $branchA = new WorkflowState('merge-2');
        $branchB = new WorkflowState('merge-2');

        $merged = WorkflowState::mergeBranches($base, ['place-a' => $branchA, 'place-b' => $branchB]);

        $this->assertContains('pre-existing', $merged->getCompletedPlaces());
        $this->assertContains('place-a', $merged->getCompletedPlaces());
        $this->assertContains('place-b', $merged->getCompletedPlaces());
        $this->assertCount(3, $merged->getCompletedPlaces());
    }

    public function testMergeBranchesDoesNotDuplicateAlreadyCompletedPlaces()
    {
        $base = new WorkflowState('merge-3', [], ['place-a']);

        $branchA = new WorkflowState('merge-3');
        $branchB = new WorkflowState('merge-3');

        $merged = WorkflowState::mergeBranches($base, ['place-a' => $branchA, 'place-b' => $branchB]);

        $this->assertCount(2, $merged->getCompletedPlaces());
        $this->assertSame(['place-a', 'place-b'], $merged->getCompletedPlaces());
    }

    public function testMergeBranchesPreservesBaseId()
    {
        $base = new WorkflowState('my-run-id');
        $branch = new WorkflowState('my-run-id');

        $merged = WorkflowState::mergeBranches($base, ['branch' => $branch]);

        $this->assertSame('my-run-id', $merged->getId());
    }

    public function testMergeBranchesClearsCurrentPlace()
    {
        $base = (new WorkflowState('merge-5'))->withCurrentPlace('some-place');
        $branch = new WorkflowState('merge-5');

        $merged = WorkflowState::mergeBranches($base, ['branch' => $branch]);

        $this->assertNull($merged->getCurrentPlace());
    }

    public function testMergeBranchesFailOnConflictThrowsWorkflowMergeConflictException()
    {
        $base = new WorkflowState('merge-6');

        $branchA = (new WorkflowState('merge-6'))->set('contested', 'value-from-a');
        $branchB = (new WorkflowState('merge-6'))->set('contested', 'value-from-b');

        $this->expectException(WorkflowMergeConflictException::class);

        WorkflowState::mergeBranches($base, ['place-a' => $branchA, 'place-b' => $branchB], MergePolicy::FailOnConflict);
    }

    public function testMergeBranchesLastBranchWinsOnConflict()
    {
        $base = new WorkflowState('merge-7');

        $branchA = (new WorkflowState('merge-7'))->set('contested', 'first');
        $branchB = (new WorkflowState('merge-7'))->set('contested', 'second');

        $merged = WorkflowState::mergeBranches($base, ['place-a' => $branchA, 'place-b' => $branchB], MergePolicy::LastBranchWins);

        $this->assertSame('second', $merged->get('contested'));
    }

    public function testMergeBranchesFirstBranchWinsOnConflict()
    {
        $base = new WorkflowState('merge-8');

        $branchA = (new WorkflowState('merge-8'))->set('contested', 'first');
        $branchB = (new WorkflowState('merge-8'))->set('contested', 'second');

        $merged = WorkflowState::mergeBranches($base, ['place-a' => $branchA, 'place-b' => $branchB], MergePolicy::FirstBranchWins);

        $this->assertSame('first', $merged->get('contested'));
    }

    public function testMergeBranchesPreferNonNullReturnsFirstNonNullValue()
    {
        $base = new WorkflowState('merge-9');

        $branchA = (new WorkflowState('merge-9'))->set('nullable', null);
        $branchB = (new WorkflowState('merge-9'))->set('nullable', 'actual-value');

        $merged = WorkflowState::mergeBranches($base, ['place-a' => $branchA, 'place-b' => $branchB], MergePolicy::PreferNonNull);

        $this->assertSame('actual-value', $merged->get('nullable'));
    }

    public function testMergeBranchesPreferNonNullFallsBackToLastWhenAllNull()
    {
        $base = new WorkflowState('merge-10');

        $branchA = (new WorkflowState('merge-10'))->set('nullable', null);
        $branchB = (new WorkflowState('merge-10'))->set('nullable', null);

        $merged = WorkflowState::mergeBranches($base, ['place-a' => $branchA, 'place-b' => $branchB], MergePolicy::PreferNonNull);

        $this->assertNull($merged->get('nullable'));
    }

    public function testMergeBranchesAgreingWriteIsNotAConflict()
    {
        $base = new WorkflowState('merge-11');

        $branchA = (new WorkflowState('merge-11'))->set('status', 'done');
        $branchB = (new WorkflowState('merge-11'))->set('status', 'done');

        // Same value from both branches — must not throw even with FailOnConflict.
        $merged = WorkflowState::mergeBranches($base, ['place-a' => $branchA, 'place-b' => $branchB], MergePolicy::FailOnConflict);

        $this->assertSame('done', $merged->get('status'));
    }

    public function testMergeBranchesBaseValueUnchangedByBranchUnset()
    {
        // A branch unsetting a base key must not propagate (unsets are not tracked as writes).
        $base = new WorkflowState('merge-12', ['keep-me' => 'yes']);

        $branchA = (new WorkflowState('merge-12', ['keep-me' => 'yes']))->unset('keep-me');

        $merged = WorkflowState::mergeBranches($base, ['place-a' => $branchA]);

        // The base value is preserved because the branch diff only sees value changes, not unsets.
        // (The engine doc says "a branch unsetting a base key does not propagate".)
        $this->assertSame('yes', $merged->get('keep-me'));
    }

    public function testMergeBranchesWithEmptyBranchArrayReturnsBaseEquivalent()
    {
        $base = new WorkflowState('merge-13', ['key' => 'value'], ['done-place']);

        $merged = WorkflowState::mergeBranches($base, []);

        $this->assertSame('value', $merged->get('key'));
        $this->assertSame(['done-place'], $merged->getCompletedPlaces());
    }

    public function testMergeBranchesConflictingNextTransitionThrows()
    {
        $base = new WorkflowState('merge-14');

        $branchA = (new WorkflowState('merge-14'))->withNextTransition('approve');
        $branchB = (new WorkflowState('merge-14'))->withNextTransition('reject');

        $this->expectException(WorkflowMergeConflictException::class);

        WorkflowState::mergeBranches($base, ['place-a' => $branchA, 'place-b' => $branchB]);
    }

    public function testMergeBranchesAgreingNextTransitionIsKept()
    {
        $base = new WorkflowState('merge-15');

        $branchA = (new WorkflowState('merge-15'))->withNextTransition('approve');
        $branchB = (new WorkflowState('merge-15'))->withNextTransition('approve');

        $merged = WorkflowState::mergeBranches($base, ['place-a' => $branchA, 'place-b' => $branchB]);

        $this->assertSame('approve', $merged->getNextTransition());
    }

    public function testMergeBranchesWithNullNextTransitionOnOneBranchIsIgnored()
    {
        $base = new WorkflowState('merge-16');

        $branchA = (new WorkflowState('merge-16'))->withNextTransition('forward');
        $branchB = new WorkflowState('merge-16');  // no transition set

        $merged = WorkflowState::mergeBranches($base, ['place-a' => $branchA, 'place-b' => $branchB]);

        $this->assertSame('forward', $merged->getNextTransition());
    }

    // --- Partial-failure resume: getInterruptedFork / withInterruptedFork / getUpdatedAt ---

    public function testGetInterruptedForkDefaultsToEmptyArray()
    {
        $state = new WorkflowState('fork-default');

        $this->assertSame([], $state->getInterruptedFork());
    }

    public function testWithInterruptedForkReturnsNewInstanceWithPlaces()
    {
        $state = new WorkflowState('fork-1');
        $result = $state->withInterruptedFork(['branch-a', 'branch-b']);

        $this->assertNotSame($state, $result);
        $this->assertSame(['branch-a', 'branch-b'], $result->getInterruptedFork());
    }

    public function testWithInterruptedForkLeavesOriginalUnchanged()
    {
        $state = new WorkflowState('fork-2');
        $state->withInterruptedFork(['branch-a', 'branch-b']);

        $this->assertSame([], $state->getInterruptedFork());
    }

    public function testInterruptedForkPreservedAcrossSet()
    {
        $state = (new WorkflowState('fork-3'))->withInterruptedFork(['p1', 'p2']);
        $result = $state->set('key', 'value');

        $this->assertSame(['p1', 'p2'], $result->getInterruptedFork());
    }

    public function testInterruptedForkPreservedAcrossWithCurrentPlace()
    {
        $state = (new WorkflowState('fork-4'))->withInterruptedFork(['p1', 'p2']);
        $result = $state->withCurrentPlace('some-place');

        $this->assertSame(['p1', 'p2'], $result->getInterruptedFork());
    }

    public function testInterruptedForkPreservedAcrossMarkCompleted()
    {
        $state = (new WorkflowState('fork-5'))->withInterruptedFork(['p1', 'p2']);
        $result = $state->markCompleted('p1');

        $this->assertSame(['p1', 'p2'], $result->getInterruptedFork());
    }

    public function testInterruptedForkPreservedAcrossWithNextTransition()
    {
        $state = (new WorkflowState('fork-6'))->withInterruptedFork(['p1', 'p2']);
        $result = $state->withNextTransition('my-transition');

        $this->assertSame(['p1', 'p2'], $result->getInterruptedFork());
    }

    public function testInterruptedForkPreservedAcrossClearNextTransition()
    {
        $state = (new WorkflowState('fork-7'))->withNextTransition('t')->withInterruptedFork(['p1', 'p2']);
        $result = $state->clearNextTransition();

        $this->assertSame(['p1', 'p2'], $result->getInterruptedFork());
    }

    public function testInterruptedForkPreservedAcrossMerge()
    {
        $state = (new WorkflowState('fork-8'))->withInterruptedFork(['p1', 'p2']);
        $result = $state->merge(['extra' => 'data']);

        $this->assertSame(['p1', 'p2'], $result->getInterruptedFork());
    }

    public function testGetUpdatedAtIsNullByDefault()
    {
        $state = new WorkflowState('updated-at-1');

        $this->assertNull($state->getUpdatedAt());
    }

    public function testGetUpdatedAtReturnsValuePassedToConstructor()
    {
        $updatedAt = new \DateTimeImmutable('2025-01-15 12:00:00');
        $state = new WorkflowState('updated-at-2', [], [], null, null, [], $updatedAt);

        $this->assertSame($updatedAt, $state->getUpdatedAt());
    }

    public function testMergeBranchesProducesStateWithEmptyInterruptedFork()
    {
        $base = (new WorkflowState('fork-merge-1'))->withInterruptedFork(['branch-a', 'branch-b', 'branch-c']);

        $branchA = (new WorkflowState('fork-merge-1'))->set('result-a', 'done');
        $branchB = (new WorkflowState('fork-merge-1'))->set('result-b', 'done');

        $merged = WorkflowState::mergeBranches($base, ['branch-a' => $branchA, 'branch-b' => $branchB]);

        $this->assertSame([], $merged->getInterruptedFork());
    }
}
