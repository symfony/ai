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

use Symfony\AI\Platform\Event\InvocationEvent;
use Symfony\AI\Platform\Event\ResultEvent;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * A provider encapsulating a single inference backend.
 *
 * This class contains the logic previously in Platform: model catalog lookup,
 * contract normalization, ModelClient dispatch, and ResultConverter dispatch.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Provider implements ProviderInterface
{
    /**
     * @param non-empty-string                   $name
     * @param iterable<ModelClientInterface>     $modelClients
     * @param iterable<ResultConverterInterface> $resultConverters
     */
    public function __construct(
        private readonly string $name,
        private readonly iterable $modelClients,
        private readonly iterable $resultConverters,
        private readonly ModelCatalogInterface $modelCatalog,
        private ?Contract $contract = null,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        $this->contract = $contract ?? Contract::create();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function supports(string $modelName): bool
    {
        try {
            $this->modelCatalog->getModel($modelName);

            return true;
        } catch (ModelNotFoundException) {
            return false;
        }
    }

    public function invoke(string $model, array|string|object $input, array $options = []): DeferredResult
    {
        $model = $this->modelCatalog->getModel($model);

        $event = new InvocationEvent($model, $input, $options);
        $this->eventDispatcher?->dispatch($event);

        $payload = $this->contract->createRequestPayload($event->getModel(), $event->getInput(), $event->getOptions());
        $options = array_merge($model->getOptions(), $event->getOptions());

        if (isset($options['tools'])) {
            $options['tools'] = $this->contract->createToolOption($options['tools'], $model);
        }

        $result = $this->convertResult($model, $this->doInvoke($model, $payload, $options), $options);

        $event = new ResultEvent($model, $result, $options, $input);
        $this->eventDispatcher?->dispatch($event);

        return $event->getDeferredResult();
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        return $this->modelCatalog;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    private function doInvoke(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        foreach ($this->modelClients as $modelClient) {
            if ($modelClient->supports($model)) {
                return $modelClient->request($model, $payload, $options);
            }
        }

        throw new RuntimeException(\sprintf('No ModelClient registered for model "%s" in provider "%s".', $model::class, $this->name));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function convertResult(Model $model, RawResultInterface $result, array $options): DeferredResult
    {
        foreach ($this->resultConverters as $resultConverter) {
            if ($resultConverter->supports($model)) {
                return new DeferredResult($resultConverter, $result, $options);
            }
        }

        throw new RuntimeException(\sprintf('No ResultConverter registered for model "%s" in provider "%s".', $model::class, $this->name));
    }
}
