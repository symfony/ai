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

/**
 * Immutable state bag flowing through a workflow run.
 *
 * Every mutating method MUST return a new instance and leave the receiver
 * untouched, so that each persisted snapshot is independent.
 *
 * Values placed in the bag must be JSON-serializable to survive a {@see AgentWorkflowInterface::resume()}
 * from a non-InMemory store (Cache/Redis/Filesystem serialize the state to JSON): a bare object such
 * as a MessageBag is only kept intact by the InMemory store or within a single, non-resumed run.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
interface WorkflowStateInterface
{
    /**
     * @return non-empty-string
     */
    public function getId(): string;

    /**
     * @return array<string, mixed>
     */
    public function all(): array;

    public function has(string $key): bool;

    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): self;

    public function unset(string $key): self;

    /**
     * @param array<string, mixed> $data
     */
    public function merge(array $data): self;

    /**
     * @return non-empty-string|null
     */
    public function getCurrentPlace(): ?string;

    /**
     * @param non-empty-string|null $place
     */
    public function withCurrentPlace(?string $place): self;

    /**
     * @return list<non-empty-string>
     */
    public function getCompletedPlaces(): array;

    /**
     * Append the given place to the completed places and clear the current place.
     *
     * @param non-empty-string $place
     */
    public function markCompleted(string $place): self;

    /**
     * @return non-empty-string|null
     */
    public function getNextTransition(): ?string;

    /**
     * @param non-empty-string $transitionName
     */
    public function withNextTransition(string $transitionName): self;

    public function clearNextTransition(): self;

    /**
     * Places of an AND-split that was interrupted mid-run; an empty list means no interrupted fork.
     *
     * @return list<non-empty-string>
     */
    public function getInterruptedFork(): array;

    /**
     * @param list<non-empty-string> $places
     */
    public function withInterruptedFork(array $places): self;

    /**
     * When the state was last persisted, or null if it has never been persisted.
     */
    public function getUpdatedAt(): ?\DateTimeImmutable;
}
