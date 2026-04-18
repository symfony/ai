<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ModelFailover;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;

/**
 * A PlatformInterface decorator that tries multiple models in sequence
 * on a single underlying platform.
 *
 * This complements {@see \Symfony\AI\Platform\Bridge\Failover\FailoverPlatform}
 * (which chains platforms) for the case where a single provider offers multiple
 * models and some may be temporarily unavailable or rate-limited.
 *
 * @author Kevin Mauel <kevin.mauel2+github@gmail.com>
 */
final class ModelFailoverPlatform implements PlatformInterface
{
    /**
     * @param non-empty-list<non-empty-string> $models
     */
    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly array $models,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        if ([] === $models) {
            throw new InvalidArgumentException(\sprintf('"%s" must have at least one model configured.', self::class));
        }
    }

    public function invoke(string $model, object|array|string $input, array $options = []): DeferredResult
    {
        $modelsToTry = [$model, ...array_filter($this->models, static fn (string $m): bool => $m !== $model)];

        foreach ($modelsToTry as $candidateModel) {
            try {
                $result = $this->platform->invoke($candidateModel, $input, $options);

                // Force eager evaluation: DeferredResult is lazy, so model-level
                // errors (rate limits, unavailable models) only surface when the
                // result is consumed — not during invoke(). Calling getResult()
                // triggers conversion and catches these errors. The result is
                // cached internally, so subsequent asText()/asObject() calls
                // by the consumer are free.
                $result->getResult();

                return $result;
            } catch (\Throwable $throwable) {
                $this->logger->error('The model "{model}" failed on platform "{platform}": {message}', [
                    'model' => $candidateModel,
                    'platform' => $this->platform::class,
                    'message' => $throwable->getMessage(),
                    'exception' => $throwable,
                ]);

                continue;
            }
        }

        throw new RuntimeException(\sprintf('All models failed on platform "%s".', $this->platform::class));
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        return $this->platform->getModelCatalog();
    }
}
