<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\Exception\LogicException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class FailoverPlatform implements PlatformInterface
{
    /**
     * @var \SplObjectStorage<PlatformInterface, int>
     */
    private \SplObjectStorage $deadPlatforms;

    /**
     * @param PlatformInterface[] $platforms
     */
    public function __construct(
        private readonly iterable $platforms,
        private readonly ClockInterface $clock = new MonotonicClock(),
        private readonly int $retryPeriod = 60,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        if ([] === $platforms) {
            throw new LogicException(\sprintf('"%s" must have at least one platform configured.', self::class));
        }

        $this->deadPlatforms = new \SplObjectStorage();
    }

    public function invoke(string $model, object|array|string $input, array $options = []): DeferredResult
    {
        return $this->do(static fn (PlatformInterface $platform): DeferredResult => $platform->invoke($model, $input, $options));
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        return $this->do(static fn (PlatformInterface $platform): ModelCatalogInterface => $platform->getModelCatalog());
    }

    private function do(\Closure $func): DeferredResult|ModelCatalogInterface
    {
        foreach ($this->platforms as $platform) {
            if ($this->deadPlatforms->offsetExists($platform) && ($this->clock->now()->getTimestamp() - $this->deadPlatforms[$platform]) > $this->retryPeriod) {
                $this->deadPlatforms->offsetUnset($platform);
            }

            if ($this->deadPlatforms->offsetExists($platform)) {
                continue;
            }

            try {
                return $func($platform);
            } catch (\Throwable $throwable) {
                $this->deadPlatforms->offsetSet($platform, $this->clock->now()->getTimestamp());

                $this->logger->warning('The {platform} platform has encountered an exception: {exception}', [
                    'platform' => $platform::class,
                    'exception' => $throwable->getMessage(),
                ]);

                continue;
            }
        }

        throw new RuntimeException('All platforms failed.');
    }
}
