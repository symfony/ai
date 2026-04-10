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

use Symfony\AI\Platform\Event\ModelRoutingEvent;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\ModelCatalog\CompositeModelCatalog;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\ModelResolver\CatalogBasedModelResolver;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Routes model invocations to the appropriate provider.
 *
 * Platform is the user-facing entry point that holds one or more providers
 * and uses a ModelResolver to determine which provider handles each request.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Platform implements PlatformInterface
{
    /**
     * @var ProviderInterface[]
     */
    private readonly array $providers;

    /**
     * @param iterable<ProviderInterface> $providers
     */
    public function __construct(
        iterable $providers,
        private readonly ModelResolverInterface $modelResolver = new CatalogBasedModelResolver(),
        private readonly ?ModelCatalogInterface $modelCatalog = null,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        $this->providers = $providers instanceof \Traversable ? iterator_to_array($providers) : $providers;

        if ([] === $this->providers) {
            throw new InvalidArgumentException('Platform must have at least one provider configured.');
        }
    }

    /**
     * Convenience factory for single-provider usage.
     */
    public static function create(ProviderInterface $provider, ?EventDispatcherInterface $eventDispatcher = null): self
    {
        return new self(
            [$provider],
            new CatalogBasedModelResolver(),
            $provider->getModelCatalog(),
            $eventDispatcher,
        );
    }

    public function invoke(string $model, array|string|object $input, array $options = []): DeferredResult
    {
        $event = new ModelRoutingEvent($model, $input, $options);
        $this->eventDispatcher?->dispatch($event);

        $provider = $this->modelResolver->resolve(
            $event->getModel(),
            $this->providers,
            $event->getInput(),
            $event->getOptions(),
        );

        return $provider->invoke($event->getModel(), $event->getInput(), $event->getOptions());
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        if (null !== $this->modelCatalog) {
            return $this->modelCatalog;
        }

        return new CompositeModelCatalog(
            array_map(
                static fn (ProviderInterface $provider): ModelCatalogInterface => $provider->getModelCatalog(),
                $this->providers,
            ),
        );
    }
}
