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
use Symfony\AI\Platform\Job\JobClientInterface;
use Symfony\AI\Platform\Job\JobHandle;
use Symfony\AI\Platform\Job\JobProviderInterface;
use Symfony\AI\Platform\ModelCatalog\CompositeModelCatalog;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\ModelRouter\CatalogBasedModelRouter;
use Symfony\AI\Platform\ModelRouter\RoutingDecision;
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
        $event = new ModelRoutingEvent($model, $input, $options);
        $this->eventDispatcher?->dispatch($event);

        $decision = null !== $event->getProvider()
            ? new RoutingDecision($event->getProvider())
            : $this->modelRouter->resolve($event->getModel(), $this->providers, $event->getInput(), $event->getOptions());

        return $decision->getProvider()->invoke(
            $decision->getModel() ?? $event->getModel(),
            $event->getInput(),
            $decision->getOptions() ?? $event->getOptions(),
        );
    }

    /**
     * Returns the job client of the provider a {@see JobHandle} belongs to.
     *
     * This is how an asynchronous job is picked up again, typically in a different process than the
     * one that started it: store the handle, rebuild it with `JobHandle::fromArray()`, and ask the
     * platform for the client that can resolve it.
     *
     *     $status = $platform->getJobClient($handle)->getStatus($handle);
     *
     * @throws InvalidArgumentException when the handle names no provider, or names one this platform
     *                                  does not have or that cannot run jobs
     */
    public function getJobClient(JobHandle $handle): JobClientInterface
    {
        $name = $handle->getProvider();

        if (null === $name) {
            throw new InvalidArgumentException(\sprintf('The job handle "%s" is not bound to a provider.', $handle->getId()));
        }

        foreach ($this->providers as $provider) {
            if ($provider->getName() !== $name) {
                continue;
            }

            if (!$provider instanceof JobProviderInterface || null === $jobClient = $provider->getJobClient()) {
                throw new InvalidArgumentException(\sprintf('The provider "%s" does not run asynchronous jobs.', $name));
            }

            return $jobClient;
        }

        throw new InvalidArgumentException(\sprintf('No provider named "%s" is configured on this platform.', $name));
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
}
