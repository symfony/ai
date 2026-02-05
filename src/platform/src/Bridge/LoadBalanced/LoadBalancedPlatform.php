<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\LoadBalanced;

use Symfony\AI\Platform\Bridge\LoadBalanced\Exception\CapacityExhaustedException;
use Symfony\AI\Platform\Bridge\LoadBalanced\Strategy\PlatformSelectionStrategy;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;

/**
 * A platform that load balances requests across multiple platforms.
 *
 * Platforms are selected based on the configured strategy and their
 * available capacity (rate limits, concurrency limits, etc.).
 *
 * @author Paul Clegg <hello@clegginabox.co.uk>
 */
final class LoadBalancedPlatform implements PlatformInterface
{
    /**
     * @var array<PlatformCapacity>
     */
    private readonly array $platforms;

    /**
     * @param iterable<PlatformCapacity> $platforms
     */
    public function __construct(
        iterable $platforms,
        private readonly PlatformSelectionStrategy $strategy,
    ) {
        $platformsArray = $platforms instanceof \Traversable ? iterator_to_array($platforms) : $platforms;

        if ([] === $platformsArray) {
            throw new InvalidArgumentException(\sprintf('"%s" must have at least one platform configured.', self::class));
        }

        $this->platforms = $platformsArray;
    }

    public function invoke(string $model, object|array|string $input, array $options = []): DeferredResult
    {
        return $this->execute(
            static fn (PlatformCapacity $capacity): DeferredResult => $capacity->platform->invoke($capacity->model ?? $model, $input, $options),
        );
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        return $this->execute(
            static fn (PlatformInterface $platform): ModelCatalogInterface => $platform->getModelCatalog()
        );
    }

    private function execute(\Closure $operation): DeferredResult|ModelCatalogInterface
    {
        foreach ($this->strategy->order($this->platforms) as $entry) {
            if (!$entry->capacityProvider->tryAcquire()) {
                continue;
            }

            try {
                return $operation($entry);
            } finally {
                $entry->capacityProvider->release();
            }
        }

        throw new CapacityExhaustedException('All platforms have exhausted their capacity.');
    }
}
