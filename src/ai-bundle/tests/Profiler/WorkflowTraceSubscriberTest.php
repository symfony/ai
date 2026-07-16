<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Tests\Profiler;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Workflow\Event\PlaceCompletedEvent;
use Symfony\AI\Agent\Workflow\Event\PlaceEnteredEvent;
use Symfony\AI\Agent\Workflow\Event\TransitionAppliedEvent;
use Symfony\AI\Agent\Workflow\Event\WorkflowCompletedEvent;
use Symfony\AI\Agent\Workflow\Event\WorkflowFailedEvent;
use Symfony\AI\Agent\Workflow\Event\WorkflowStartedEvent;
use Symfony\AI\Agent\Workflow\WorkflowState;
use Symfony\AI\AiBundle\Exception\RuntimeException;
use Symfony\AI\AiBundle\Profiler\WorkflowTraceSubscriber;
use Symfony\Component\Clock\MockClock;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class WorkflowTraceSubscriberTest extends TestCase
{
    public function testGetSubscribedEventsReturnsAllSixEvents()
    {
        $subscribedEvents = WorkflowTraceSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(WorkflowStartedEvent::class, $subscribedEvents);
        $this->assertArrayHasKey(PlaceEnteredEvent::class, $subscribedEvents);
        $this->assertArrayHasKey(PlaceCompletedEvent::class, $subscribedEvents);
        $this->assertArrayHasKey(TransitionAppliedEvent::class, $subscribedEvents);
        $this->assertArrayHasKey(WorkflowCompletedEvent::class, $subscribedEvents);
        $this->assertArrayHasKey(WorkflowFailedEvent::class, $subscribedEvents);

        $this->assertSame('onWorkflowStarted', $subscribedEvents[WorkflowStartedEvent::class]);
        $this->assertSame('onPlaceEntered', $subscribedEvents[PlaceEnteredEvent::class]);
        $this->assertSame('onPlaceCompleted', $subscribedEvents[PlaceCompletedEvent::class]);
        $this->assertSame('onTransitionApplied', $subscribedEvents[TransitionAppliedEvent::class]);
        $this->assertSame('onWorkflowCompleted', $subscribedEvents[WorkflowCompletedEvent::class]);
        $this->assertSame('onWorkflowFailed', $subscribedEvents[WorkflowFailedEvent::class]);
    }

    public function testTimelineIsEmptyBeforeAnyEvents()
    {
        $subscriber = new WorkflowTraceSubscriber();

        $this->assertSame([], $subscriber->getTimeline());
    }

    public function testOnWorkflowStartedRecordsStartedEntry()
    {
        $clock = new MockClock('2024-01-01 10:00:00');
        $subscriber = new WorkflowTraceSubscriber($clock);
        $state = new WorkflowState('state-001');

        $subscriber->onWorkflowStarted(new WorkflowStartedEvent($state, resume: false));

        $timeline = $subscriber->getTimeline();

        $this->assertCount(1, $timeline);
        $this->assertSame('started', $timeline[0]['type']);
        $this->assertSame('state-001', $timeline[0]['state_id']);
        $this->assertNull($timeline[0]['place']);
        $this->assertNull($timeline[0]['transition']);
        $this->assertNull($timeline[0]['error']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $timeline[0]['at']);
    }

    public function testOnWorkflowStartedRecordsResumedEntryWhenResume()
    {
        $clock = new MockClock('2024-01-01 10:00:00');
        $subscriber = new WorkflowTraceSubscriber($clock);
        $state = new WorkflowState('state-resume-01');

        $subscriber->onWorkflowStarted(new WorkflowStartedEvent($state, resume: true));

        $timeline = $subscriber->getTimeline();

        $this->assertCount(1, $timeline);
        $this->assertSame('resumed', $timeline[0]['type']);
        $this->assertSame('state-resume-01', $timeline[0]['state_id']);
    }

    public function testOnPlaceEnteredRecordsPlaceEnteredEntry()
    {
        $clock = new MockClock('2024-01-01 10:00:00');
        $subscriber = new WorkflowTraceSubscriber($clock);
        $state = new WorkflowState('state-002');

        $subscriber->onPlaceEntered(new PlaceEnteredEvent($state, 'generate'));

        $timeline = $subscriber->getTimeline();

        $this->assertCount(1, $timeline);
        $this->assertSame('place_entered', $timeline[0]['type']);
        $this->assertSame('state-002', $timeline[0]['state_id']);
        $this->assertSame('generate', $timeline[0]['place']);
        $this->assertNull($timeline[0]['transition']);
        $this->assertNull($timeline[0]['error']);
    }

    public function testOnPlaceCompletedRecordsPlaceCompletedEntry()
    {
        $clock = new MockClock('2024-01-01 10:00:00');
        $subscriber = new WorkflowTraceSubscriber($clock);
        $state = new WorkflowState('state-003');

        $subscriber->onPlaceCompleted(new PlaceCompletedEvent($state, 'generate'));

        $timeline = $subscriber->getTimeline();

        $this->assertCount(1, $timeline);
        $this->assertSame('place_completed', $timeline[0]['type']);
        $this->assertSame('state-003', $timeline[0]['state_id']);
        $this->assertSame('generate', $timeline[0]['place']);
        $this->assertNull($timeline[0]['transition']);
        $this->assertNull($timeline[0]['error']);
    }

    public function testOnTransitionAppliedRecordsTransitionEntry()
    {
        $clock = new MockClock('2024-01-01 10:00:00');
        $subscriber = new WorkflowTraceSubscriber($clock);
        $state = new WorkflowState('state-004');

        $subscriber->onTransitionApplied(new TransitionAppliedEvent($state, 'to_summarize'));

        $timeline = $subscriber->getTimeline();

        $this->assertCount(1, $timeline);
        $this->assertSame('transition_applied', $timeline[0]['type']);
        $this->assertSame('state-004', $timeline[0]['state_id']);
        $this->assertNull($timeline[0]['place']);
        $this->assertSame('to_summarize', $timeline[0]['transition']);
        $this->assertNull($timeline[0]['error']);
    }

    public function testOnWorkflowCompletedRecordsCompletedEntry()
    {
        $clock = new MockClock('2024-01-01 10:00:00');
        $subscriber = new WorkflowTraceSubscriber($clock);
        $state = new WorkflowState('state-005');

        $subscriber->onWorkflowCompleted(new WorkflowCompletedEvent($state));

        $timeline = $subscriber->getTimeline();

        $this->assertCount(1, $timeline);
        $this->assertSame('completed', $timeline[0]['type']);
        $this->assertSame('state-005', $timeline[0]['state_id']);
        $this->assertNull($timeline[0]['place']);
        $this->assertNull($timeline[0]['transition']);
        $this->assertNull($timeline[0]['error']);
    }

    public function testOnWorkflowFailedRecordsFailedEntryWithError()
    {
        $clock = new MockClock('2024-01-01 10:00:00');
        $subscriber = new WorkflowTraceSubscriber($clock);
        $state = new WorkflowState('state-006');
        $exception = new RuntimeException('Something went wrong');

        $subscriber->onWorkflowFailed(new WorkflowFailedEvent($state, 'generate', $exception));

        $timeline = $subscriber->getTimeline();

        $this->assertCount(1, $timeline);
        $this->assertSame('failed', $timeline[0]['type']);
        $this->assertSame('state-006', $timeline[0]['state_id']);
        $this->assertSame('generate', $timeline[0]['place']);
        $this->assertNull($timeline[0]['transition']);
        $this->assertSame('Something went wrong', $timeline[0]['error']);
    }

    public function testOnWorkflowFailedWithNullPlaceRecordsNullPlace()
    {
        $clock = new MockClock('2024-01-01 10:00:00');
        $subscriber = new WorkflowTraceSubscriber($clock);
        $state = new WorkflowState('state-007');
        $exception = new RuntimeException('Transition guard rejected');

        $subscriber->onWorkflowFailed(new WorkflowFailedEvent($state, null, $exception));

        $timeline = $subscriber->getTimeline();

        $this->assertCount(1, $timeline);
        $this->assertSame('failed', $timeline[0]['type']);
        $this->assertSame('state-007', $timeline[0]['state_id']);
        $this->assertNull($timeline[0]['place']);
        $this->assertSame('Transition guard rejected', $timeline[0]['error']);
    }

    public function testMultipleEventsProduceMultipleTimelineEntries()
    {
        $clock = new MockClock('2024-01-01 10:00:00');
        $subscriber = new WorkflowTraceSubscriber($clock);
        $state = new WorkflowState('state-multi');

        $subscriber->onWorkflowStarted(new WorkflowStartedEvent($state));
        $subscriber->onPlaceEntered(new PlaceEnteredEvent($state, 'generate'));
        $subscriber->onPlaceCompleted(new PlaceCompletedEvent($state, 'generate'));
        $subscriber->onTransitionApplied(new TransitionAppliedEvent($state, 'to_done'));
        $subscriber->onWorkflowCompleted(new WorkflowCompletedEvent($state));

        $timeline = $subscriber->getTimeline();

        $this->assertCount(5, $timeline);
        $this->assertSame('started', $timeline[0]['type']);
        $this->assertSame('place_entered', $timeline[1]['type']);
        $this->assertSame('place_completed', $timeline[2]['type']);
        $this->assertSame('transition_applied', $timeline[3]['type']);
        $this->assertSame('completed', $timeline[4]['type']);
    }

    public function testResetClearsTimeline()
    {
        $clock = new MockClock('2024-01-01 10:00:00');
        $subscriber = new WorkflowTraceSubscriber($clock);
        $state = new WorkflowState('state-reset');

        $subscriber->onWorkflowStarted(new WorkflowStartedEvent($state));
        $subscriber->onWorkflowCompleted(new WorkflowCompletedEvent($state));

        $this->assertCount(2, $subscriber->getTimeline());

        $subscriber->reset();

        $this->assertSame([], $subscriber->getTimeline());
    }

    public function testResetOnAlreadyEmptyTimelineIsIdempotent()
    {
        $subscriber = new WorkflowTraceSubscriber();

        $subscriber->reset();

        $this->assertSame([], $subscriber->getTimeline());
    }

    public function testEachEntryCarriesAnAtTimestamp()
    {
        $clock = new MockClock('2024-06-15 12:00:00');
        $subscriber = new WorkflowTraceSubscriber($clock);
        $state = new WorkflowState('state-timestamp');

        $subscriber->onPlaceEntered(new PlaceEnteredEvent($state, 'start'));

        $timeline = $subscriber->getTimeline();

        $this->assertInstanceOf(\DateTimeImmutable::class, $timeline[0]['at']);
    }

    public function testSubscriberCanBeConstructedWithoutClock()
    {
        $subscriber = new WorkflowTraceSubscriber();
        $state = new WorkflowState('state-no-clock');

        $subscriber->onWorkflowStarted(new WorkflowStartedEvent($state));

        $timeline = $subscriber->getTimeline();

        $this->assertCount(1, $timeline);
        $this->assertInstanceOf(\DateTimeImmutable::class, $timeline[0]['at']);
    }
}
