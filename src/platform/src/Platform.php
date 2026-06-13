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
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\ModelCatalog\CompositeModelCatalog;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\ModelRouter\CatalogBasedModelRouter;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Routes model invocations to the appropriate provider.
 *
 * Platform is the user-facing entry point that holds one or more providers
 * and uses a ModelRouter to determine which provider handles each request.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Platform implements PlatformInterface
{
    private ?ModelCatalogInterface $modelCatalog = null;

    /**
     * @param ProviderInterface[] $providers
     */
    public function __construct(
        private readonly array $providers,
        private readonly ModelRouterInterface $modelRouter = new CatalogBasedModelRouter(),
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        if ([] === $this->providers) {
            throw new InvalidArgumentException('Platform must have at least one provider configured.');
        }
    }

    public function invoke(string|Model $model, array|string|object $input, array $options = []): DeferredResult
    {
        if ($model instanceof Model) {
            return $this->resolveProviderForModel($model)->invoke($model, $input, $options);
        }

        $event = new ModelRoutingEvent($model, $input, $options);
        $this->eventDispatcher?->dispatch($event);

        $provider = $event->getProvider()
            ?? $this->modelRouter->resolve($event->getModel(), $this->providers, $event->getInput(), $event->getOptions());

        return $provider->invoke($event->getModel(), $event->getInput(), $event->getOptions());
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        return $this->modelCatalog ??= new CompositeModelCatalog(
            array_map(
                static fn (ProviderInterface $provider): ModelCatalogInterface => $provider->getModelCatalog(),
                $this->providers,
            ),
        );
    }

    /**
     * Routes a fully defined model to the first provider whose model clients accept it.
     */
    private function resolveProviderForModel(Model $model): ProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($model)) {
                return $provider;
            }
        }

        if (Model::class === $model::class) {
            throw new ModelNotFoundException(\sprintf('No provider found for model "%s": a base "%s" instance has no model client. Pass a bridge-specific model subclass instead (e.g. "%s\\Bridge\\OpenAi\\Gpt", "%s\\Bridge\\Anthropic\\Claude", "%s\\Bridge\\Gemini\\Gemini").', $model->getName(), Model::class, __NAMESPACE__, __NAMESPACE__, __NAMESPACE__));
        }

        throw new ModelNotFoundException(\sprintf('No provider found for model "%s" (%s).', $model->getName(), $model::class));
    }
}
