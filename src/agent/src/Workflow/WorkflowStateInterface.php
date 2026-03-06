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
interface WorkflowStateInterface
{
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

    public function getCurrentPlace(): ?string;

    public function withCurrentPlace(?string $place): self;

    /**
     * @return list<non-empty-string>
     */
    public function getCompletedPlaces(): array;

    /**
     * @param non-empty-string $place
     */
    public function withCompletedPlace(string $place): self;

    public function getNextTransition(): ?string;

    /**
     * @param non-empty-string $transitionName
     */
    public function withNextTransition(string $transitionName): self;
}
