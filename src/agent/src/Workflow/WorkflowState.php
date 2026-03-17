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
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class WorkflowState implements WorkflowStateInterface, \Countable
{
    /**
     * @param non-empty-string       $id              Unique identifier for this workflow run
     * @param array<string, mixed>   $data            Key-value runtime data
     * @param list<non-empty-string> $completedPlaces Places already executed
     * @param non-empty-string|null  $currentPlace    The place currently being executed
     */
    public function __construct(
        private readonly string $id,
        private array $data = [],
        private array $completedPlaces = [],
        private ?string $currentPlace = null,
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
        $this->data[$key] = $value;

        return $this;
    }

    public function unset(string $key): self
    {
        unset($this->data[$key]);

        return $this;
    }

    public function merge(array $data): self
    {
        $this->data = array_merge($this->data, $data);

        return $this;
    }

    public function getCurrentPlace(): ?string
    {
        return $this->currentPlace;
    }

    public function withCurrentPlace(?string $place): self
    {
        $this->currentPlace = $place;

        return $this;
    }

    public function getCompletedPlaces(): array
    {
        return $this->completedPlaces;
    }

    public function withCompletedPlace(string $place): self
    {
        $this->completedPlaces[] = $place;

        return $this;
    }

    public function getNextTransition(): ?string
    {
        $transition = $this->get('_next_transition');

        if (null === $transition) {
            return null;
        }

        return (string) $transition;
    }

    public function withNextTransition(string $transitionName): self
    {
        return $this->set('_next_transition', $transitionName);
    }

    public function count(): int
    {
        return \count($this->data);
    }
}
