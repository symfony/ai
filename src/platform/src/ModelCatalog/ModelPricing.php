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
 * Objective, declarative pricing for a model catalog entry.
 *
 * All rates are expressed per 1,000,000 tokens, in the given currency.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ModelPricing
{
    /**
     * @param float      $inputPerMillion       Rate for (uncached) input tokens
     * @param float      $outputPerMillion      Rate for output tokens
     * @param float|null $cachedInputPerMillion Rate for cache-read (cached prompt) input tokens
     * @param float|null $cacheWritePerMillion  Rate for cache-write (cache creation) tokens
     * @param string     $currency              ISO 4217 currency code
     */
    public function __construct(
        private readonly float $inputPerMillion,
        private readonly float $outputPerMillion,
        private readonly ?float $cachedInputPerMillion = null,
        private readonly ?float $cacheWritePerMillion = null,
        private readonly string $currency = 'USD',
    ) {
    }

    public function getInputPerMillion(): float
    {
        return $this->inputPerMillion;
    }

    public function getOutputPerMillion(): float
    {
        return $this->outputPerMillion;
    }

    public function getCachedInputPerMillion(): ?float
    {
        return $this->cachedInputPerMillion;
    }

    public function getCacheWritePerMillion(): ?float
    {
        return $this->cacheWritePerMillion;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * @param array{
     *     input: float|int,
     *     output: float|int,
     *     cachedInput?: float|int,
     *     cacheWrite?: float|int,
     *     currency?: string,
     * } $pricing
     */
    public static function fromArray(array $pricing): self
    {
        return new self(
            (float) $pricing['input'],
            (float) $pricing['output'],
            isset($pricing['cachedInput']) ? (float) $pricing['cachedInput'] : null,
            isset($pricing['cacheWrite']) ? (float) $pricing['cacheWrite'] : null,
            $pricing['currency'] ?? 'USD',
        );
    }
}
