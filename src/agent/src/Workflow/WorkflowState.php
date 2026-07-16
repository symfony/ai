<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Workflow;

use Symfony\AI\Agent\Exception\WorkflowMergeConflictException;

/**
 * Immutable implementation of {@see WorkflowStateInterface}.
 *
 * Every mutating method returns a new instance, so persisting the state after
 * each step yields independent snapshots that can be safely resumed.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class WorkflowState implements WorkflowStateInterface, \Countable
{
    /**
     * @param non-empty-string        $id              Unique identifier for this workflow run
     * @param array<string, mixed>    $data            Key-value runtime data
     * @param list<non-empty-string>  $completedPlaces Places already executed
     * @param non-empty-string|null   $currentPlace    The place currently being executed
     * @param non-empty-string|null   $nextTransition  Hint for the next transition to apply
     * @param list<non-empty-string>  $interruptedFork Places of an AND-split interrupted mid-run; empty when none
     * @param \DateTimeImmutable|null $updatedAt       When the state was last persisted; null until first persisted
     */
    public function __construct(
        private readonly string $id,
        private readonly array $data = [],
        private readonly array $completedPlaces = [],
        private readonly ?string $currentPlace = null,
        private readonly ?string $nextTransition = null,
        private readonly array $interruptedFork = [],
        private readonly ?\DateTimeImmutable $updatedAt = null,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function all(): array
    {
        return $this->data;
    }

    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->data);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!\array_key_exists($key, $this->data)) {
            return $default;
        }

        return $this->data[$key];
    }

    public function set(string $key, mixed $value): self
    {
        $data = $this->data;
        $data[$key] = $value;

        return new self($this->id, $data, $this->completedPlaces, $this->currentPlace, $this->nextTransition, $this->interruptedFork);
    }

    public function unset(string $key): self
    {
        $data = $this->data;
        unset($data[$key]);

        return new self($this->id, $data, $this->completedPlaces, $this->currentPlace, $this->nextTransition, $this->interruptedFork);
    }

    public function merge(array $data): self
    {
        return new self($this->id, array_merge($this->data, $data), $this->completedPlaces, $this->currentPlace, $this->nextTransition, $this->interruptedFork);
    }

    public function getCurrentPlace(): ?string
    {
        return $this->currentPlace;
    }

    public function withCurrentPlace(?string $place): self
    {
        return new self($this->id, $this->data, $this->completedPlaces, $place, $this->nextTransition, $this->interruptedFork);
    }

    public function getCompletedPlaces(): array
    {
        return $this->completedPlaces;
    }

    public function markCompleted(string $place): self
    {
        $completedPlaces = $this->completedPlaces;
        $completedPlaces[] = $place;

        return new self($this->id, $this->data, $completedPlaces, null, $this->nextTransition, $this->interruptedFork);
    }

    public function getNextTransition(): ?string
    {
        return $this->nextTransition;
    }

    public function withNextTransition(string $transitionName): self
    {
        return new self($this->id, $this->data, $this->completedPlaces, $this->currentPlace, $transitionName, $this->interruptedFork);
    }

    public function clearNextTransition(): self
    {
        return new self($this->id, $this->data, $this->completedPlaces, $this->currentPlace, null, $this->interruptedFork);
    }

    public function getInterruptedFork(): array
    {
        return $this->interruptedFork;
    }

    public function withInterruptedFork(array $places): self
    {
        return new self($this->id, $this->data, $this->completedPlaces, $this->currentPlace, $this->nextTransition, $places);
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function count(): int
    {
        return \count($this->data);
    }

    /**
     * Merges the result states of parallel branches into a single state.
     *
     * Each branch's data is diffed against the base: a key changed by a single branch is taken as
     * is, and a key changed by several branches to different values is resolved by the merge
     * policy. Every branch place is appended to the completed places. A branch unsetting a base
     * key does not propagate.
     *
     * @param array<non-empty-string, WorkflowStateInterface> $branchStates Each branch's result state, keyed by place
     *
     * @throws WorkflowMergeConflictException When branches conflict and the policy does not resolve it
     */
    public static function mergeBranches(WorkflowStateInterface $base, array $branchStates, MergePolicy $policy = MergePolicy::FailOnConflict): self
    {
        $baseData = $base->all();
        $merged = $baseData;

        /** @var array<string, non-empty-list<array{place: string, value: mixed}>> $writes */
        $writes = [];
        foreach ($branchStates as $place => $branch) {
            foreach ($branch->all() as $key => $value) {
                if (!\array_key_exists($key, $baseData) || $baseData[$key] !== $value) {
                    $writes[$key][] = ['place' => $place, 'value' => $value];
                }
            }
        }

        foreach ($writes as $key => $keyWrites) {
            $merged[$key] = self::resolveWrites($key, $keyWrites, $policy);
        }

        $completedPlaces = $base->getCompletedPlaces();
        foreach (array_keys($branchStates) as $place) {
            if (!\in_array($place, $completedPlaces, true)) {
                $completedPlaces[] = $place;
            }
        }

        return new self($base->getId(), $merged, $completedPlaces, null, self::mergeNextTransition($branchStates));
    }

    /**
     * @param non-empty-list<array{place: string, value: mixed}> $writes
     */
    private static function resolveWrites(string $key, array $writes, MergePolicy $policy): mixed
    {
        $value = $writes[0]['value'];

        foreach ($writes as $write) {
            if ($write['value'] !== $value) {
                return $policy->resolveConflict($key, $writes);
            }
        }

        return $value;
    }

    /**
     * @param array<non-empty-string, WorkflowStateInterface> $branchStates
     *
     * @throws WorkflowMergeConflictException
     */
    private static function mergeNextTransition(array $branchStates): ?string
    {
        $transition = null;

        foreach ($branchStates as $branch) {
            $candidate = $branch->getNextTransition();

            if (null === $candidate) {
                continue;
            }

            if (null === $transition) {
                $transition = $candidate;
            } elseif ($transition !== $candidate) {
                throw new WorkflowMergeConflictException(\sprintf('Parallel branches disagree on the next transition ("%s" vs "%s").', $transition, $candidate));
            }
        }

        return $transition;
    }
}
