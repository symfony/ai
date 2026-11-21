<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Failover;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class FailoverPlatform implements PlatformInterface
{
    /**
     * @var \WeakMap<PlatformInterface, int>
     */
    private readonly \WeakMap $failedPlatforms;

    /**
     * @param PlatformInterface[] $platforms
     */
    public function __construct(
        private readonly iterable $platforms,
        private readonly RateLimiterFactoryInterface $rateLimiterFactory,
        private readonly ClockInterface $clock = new MonotonicClock(),
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        if ([] === $platforms) {
            throw new InvalidArgumentException(\sprintf('"%s" must have at least one platform configured.', self::class));
        }

        $this->failedPlatforms = new \WeakMap();
    }

    public function invoke(string|Model $model, object|array|string $input, array $options = []): DeferredResult
    {
        return $this->do(static fn (PlatformInterface $platform): DeferredResult => $platform->invoke($model, $input, $options));
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        return $this->do(static fn (PlatformInterface $platform): ModelCatalogInterface => $platform->getModelCatalog());
    }

    private function do(\Closure $func): DeferredResult|ModelCatalogInterface
    {
        $position = 0;
        $skippedPlatforms = [];

        foreach ($this->platforms as $platform) {
            $limiter = $this->rateLimiterFactory->create((string) $position++);

            if ($this->failedPlatforms->offsetExists($platform)) {
                if (!$limiter->consume()->isAccepted()) {
                    $skippedPlatforms[] = $platform;

                    continue;
                }

                $this->failedPlatforms->offsetUnset($platform);
            }

            try {
                return $func($platform);
            } catch (\Throwable $throwable) {
                $limiter->consume();

                $this->markAsFailed($platform, $throwable);
            }
        }

        // No other platform answered, so the skipped ones get a last try instead of no try at all.
        foreach ($skippedPlatforms as $platform) {
            try {
                $result = $func($platform);
            } catch (\Throwable $throwable) {
                $this->markAsFailed($platform, $throwable);

                continue;
            }

            $this->failedPlatforms->offsetUnset($platform);

            return $result;
        }

        throw new RuntimeException('All platforms failed.');
    }

    private function markAsFailed(PlatformInterface $platform, \Throwable $throwable): void
    {
        $this->failedPlatforms->offsetSet($platform, $this->clock->now()->getTimestamp());

        $this->logger->error('The {platform} platform failed due to an error/exception: {message}', [
            'platform' => $platform::class,
            'message' => $throwable->getMessage(),
            'exception' => $throwable,
        ]);
    }
}
