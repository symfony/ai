<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Profiler;

use Symfony\AI\Agent\Workflow\Event\PlaceCompletedEvent;
use Symfony\AI\Agent\Workflow\Event\PlaceEnteredEvent;
use Symfony\AI\Agent\Workflow\Event\TransitionAppliedEvent;
use Symfony\AI\Agent\Workflow\Event\WorkflowCompletedEvent;
use Symfony\AI\Agent\Workflow\Event\WorkflowFailedEvent;
use Symfony\AI\Agent\Workflow\Event\WorkflowStartedEvent;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Records the per-place timeline of every workflow run for the profiler panel.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 *
 * @phpstan-type WorkflowTraceEntry array{
 *     type: string,
 *     state_id: string,
 *     place: string|null,
 *     transition: string|null,
 *     error: string|null,
 *     at: \DateTimeImmutable,
 * }
 */
final class WorkflowTraceSubscriber implements EventSubscriberInterface, ResetInterface
{
    /**
     * @var list<WorkflowTraceEntry>
     */
    private array $timeline = [];

    public function __construct(
        private readonly ClockInterface $clock = new MonotonicClock(),
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkflowStartedEvent::class => 'onWorkflowStarted',
            PlaceEnteredEvent::class => 'onPlaceEntered',
            PlaceCompletedEvent::class => 'onPlaceCompleted',
            TransitionAppliedEvent::class => 'onTransitionApplied',
            WorkflowCompletedEvent::class => 'onWorkflowCompleted',
            WorkflowFailedEvent::class => 'onWorkflowFailed',
        ];
    }

    public function onWorkflowStarted(WorkflowStartedEvent $event): void
    {
        $this->record($event->isResume() ? 'resumed' : 'started', $event->getState()->getId());
    }

    public function onPlaceEntered(PlaceEnteredEvent $event): void
    {
        $this->record('place_entered', $event->getState()->getId(), place: $event->getPlace());
    }

    public function onPlaceCompleted(PlaceCompletedEvent $event): void
    {
        $this->record('place_completed', $event->getState()->getId(), place: $event->getPlace());
    }

    public function onTransitionApplied(TransitionAppliedEvent $event): void
    {
        $this->record('transition_applied', $event->getState()->getId(), transition: $event->getTransition());
    }

    public function onWorkflowCompleted(WorkflowCompletedEvent $event): void
    {
        $this->record('completed', $event->getState()->getId());
    }

    public function onWorkflowFailed(WorkflowFailedEvent $event): void
    {
        $this->record('failed', $event->getState()->getId(), place: $event->getPlace(), error: $event->getError()->getMessage());
    }

    /**
     * @return list<WorkflowTraceEntry>
     */
    public function getTimeline(): array
    {
        return $this->timeline;
    }

    public function reset(): void
    {
        $this->timeline = [];
    }

    private function record(string $type, string $stateId, ?string $place = null, ?string $transition = null, ?string $error = null): void
    {
        $this->timeline[] = [
            'type' => $type,
            'state_id' => $stateId,
            'place' => $place,
            'transition' => $transition,
            'error' => $error,
            'at' => $this->clock->now(),
        ];
    }
}
