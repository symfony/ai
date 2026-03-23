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
     * @var list<PlatformInterface>
     */
    private readonly array $platforms;

    /**
     * @var \WeakMap<PlatformInterface, non-empty-string>
     */
    private \WeakMap $modelOverrides;

    /**
     * @var \WeakMap<PlatformInterface, int>
     */
    private readonly \WeakMap $failedPlatforms;

    /**
     * @param list<array{platform: PlatformInterface, model?: string|null}> $platforms
     */
    public function __construct(
        array $platforms,
        private readonly RateLimiterFactoryInterface $rateLimiterFactory,
        private readonly ClockInterface $clock = new MonotonicClock(),
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        if ([] === $platforms) {
            throw new InvalidArgumentException(\sprintf('"%s" must have at least one platform configured.', self::class));
        }

        $this->modelOverrides = new \WeakMap();
        $resolvedPlatforms = [];

        foreach ($platforms as $config) {
            $platform = $config['platform'];
            $model = $config['model'] ?? null;

            if (\is_string($model) && '' !== $model) {
                $this->modelOverrides[$platform] = $model;
            }

            $resolvedPlatforms[] = $platform;
        }

        $this->platforms = $resolvedPlatforms;
        $this->failedPlatforms = new \WeakMap();
    }

    public function invoke(string $model, object|array|string $input, array $options = []): DeferredResult
    {
        $modelOverrides = $this->modelOverrides;

        return $this->do(static fn (PlatformInterface $platform): DeferredResult => $platform->invoke(
            $modelOverrides[$platform] ?? $model,
            $input,
            $options,
        ));
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        return $this->do(static fn (PlatformInterface $platform): ModelCatalogInterface => $platform->getModelCatalog());
    }

    /**
     * @template T of DeferredResult|ModelCatalogInterface
     *
     * @param \Closure(PlatformInterface): T $func
     *
     * @return T
     */
    private function do(\Closure $func): DeferredResult|ModelCatalogInterface
    {
        foreach ($this->platforms as $platform) {
            $limiter = $this->rateLimiterFactory->create($platform::class);

            try {
                if ($limiter->consume()->isAccepted() && $this->failedPlatforms->offsetExists($platform)) {
                    $this->failedPlatforms->offsetUnset($platform);
                }

                return $func($platform);
            } catch (\Throwable $throwable) {
                $limiter->consume();

                $this->failedPlatforms->offsetSet($platform, $this->clock->now()->getTimestamp());

                $this->logger->error('The {platform} platform failed due to an error/exception: {message}', [
                    'platform' => $platform::class,
                    'message' => $throwable->getMessage(),
                    'exception' => $throwable,
                ]);

                continue;
            }
        }

        throw new RuntimeException('All platforms failed.');
    }
}
