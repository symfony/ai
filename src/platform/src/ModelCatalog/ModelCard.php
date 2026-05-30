<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\ModelCatalog;

/**
 * Objective, declarative facts about a model catalog entry.
 *
 * Carries platform-blessed facts (pricing, region) as typed fields, plus an open
 * `extra` bag for app-specific values. Platform code never branches on the contents
 * of `extra`.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ModelCard
{
    /**
     * @param array<string, mixed> $extra App-specific values (e.g. tier, scores).
     *                                    Conventional keys: "tier", "modelTier", "scores" —
     *                                    documented so apps converge, but NOT enforced.
     */
    public function __construct(
        private readonly ?ModelPricing $pricing = null,
        private readonly ?string $region = null,
        private readonly array $extra = [],
    ) {
    }

    public function getPricing(): ?ModelPricing
    {
        return $this->pricing;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    /**
     * Reads a value from the open `extra` bag.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->extra[$key] ?? $default;
    }

    /**
     * @return array<string, mixed> The `extra` bag
     */
    public function all(): array
    {
        return $this->extra;
    }

    /**
     * @param array{
     *     pricing?: array<string, mixed>,
     *     region?: string|null,
     *     extra?: array<string, mixed>,
     * } $card
     */
    public static function fromArray(array $card): self
    {
        return new self(
            isset($card['pricing']) ? ModelPricing::fromArray($card['pricing']) : null,
            $card['region'] ?? null,
            $card['extra'] ?? [],
        );
    }
}
