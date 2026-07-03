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

use Symfony\AI\Platform\Batch\BatchInput;
use Symfony\AI\Platform\Batch\BatchResultConverterInterface;
use Symfony\AI\Platform\Batch\BatchSubmitClientInterface;
use Symfony\AI\Platform\Event\InvocationEvent;
use Symfony\AI\Platform\Event\ResultConvertedEvent;
use Symfony\AI\Platform\Event\ResultErrorEvent;
use Symfony\AI\Platform\Event\ResultEvent;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * A provider encapsulating a single inference backend.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Provider implements ProviderInterface
{
    /**
     * Caches the most recently resolved model so supports() and invoke() in
     * the same invocation cycle don't hit API-backed catalogs twice.
     */
    private ?Model $resolvedModel = null;

    /**
     * @param non-empty-string                     $name
     * @param iterable<ModelClientInterface>       $modelClients
     * @param iterable<ResultConverterInterface>   $resultConverters
     * @param iterable<BatchSubmitClientInterface> $batchSubmitClients Clients handling `['batch' => true]` submissions
     */
    public function __construct(
        private readonly string $name,
        private readonly iterable $modelClients,
        private readonly iterable $resultConverters,
        private readonly ModelCatalogInterface $modelCatalog,
        private ?Contract $contract = null,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly iterable $batchSubmitClients = [],
    ) {
        $this->contract = $contract ?? Contract::create();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function supports(string|Model $model): bool
    {
        if ($model instanceof Model) {
            foreach ($this->modelClients as $modelClient) {
                if ($modelClient->supports($model)) {
                    return true;
                }
            }

            return false;
        }

        try {
            $this->resolvedModel = $this->modelCatalog->getModel($model);

            return true;
        } catch (ModelNotFoundException) {
            $this->resolvedModel = null;

            return false;
        }
    }

    public function invoke(string|Model $model, array|string|object $input, array $options = []): DeferredResult
    {
        if ($model instanceof Model) {
            $this->resolvedModel = null;
        } elseif (null !== $this->resolvedModel && $this->resolvedModel->getName() === $model) {
            $model = $this->resolvedModel;
            $this->resolvedModel = null;
        } else {
            $model = $this->modelCatalog->getModel($model);
        }

        $invocationEvent = new InvocationEvent($model, $input, $options);
        $this->eventDispatcher?->dispatch($invocationEvent);

        $model = $invocationEvent->getModel();
        $input = $invocationEvent->getInput();
        $options = $invocationEvent->getOptions();

        if ($options['batch'] ?? false) {
            return $this->invokeBatch($model, $input, $options);
        }

        $payload = $this->contract->createRequestPayload($model, $input, $options);
        $options = array_merge($model->getOptions(), $options);

        if (isset($options['tools'])) {
            $options['tools'] = $this->contract->createToolOption($options['tools'], $model);
        }

        $result = $this->convertResult($model, $this->doInvoke($model, $payload, $options), $options);

        $resultEvent = new ResultEvent($model, $result, $options, $input);
        $this->eventDispatcher?->dispatch($resultEvent);

        // Register on the deferred result the ResultEvent listeners ultimately produced, since a
        // listener may swap it out via setDeferredResult(). A listener that resolves the result
        // eagerly (defeating the laziness) would therefore convert before these are registered and
        // miss ResultConvertedEvent.
        $deferredResult = $resultEvent->getDeferredResult();

        if (null !== $this->eventDispatcher) {
            $deferredResult->onConvert(function (ResultInterface $result) use ($model, $options, $input): ResultInterface {
                $event = new ResultConvertedEvent($model, $result, $options, $input);
                $this->eventDispatcher->dispatch($event);

                return $event->getResult();
            });
            $deferredResult->onError(function (\Throwable $error) use ($model, $options, $input): void {
                $this->eventDispatcher->dispatch(new ResultErrorEvent($model, $error, $options, $input));
            });
        }

        return $deferredResult;
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        return $this->modelCatalog;
    }

    /**
     * @param array<string, mixed>|string $payload
     * @param array<string, mixed>        $options
     */
    private function doInvoke(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        foreach ($this->modelClients as $modelClient) {
            if ($modelClient->supports($model)) {
                return $modelClient->request($model, $payload, $options);
            }
        }

        throw new RuntimeException(\sprintf('No ModelClient registered for model "%s" (%s) in provider "%s".', $model->getName(), $model::class, $this->name));
    }

    /**
     * @param iterable<BatchInput> $inputs
     * @param array<string, mixed> $options
     */
    private function invokeBatch(Model $model, iterable $inputs, array $options): DeferredResult
    {
        unset($options['batch']);
        $options = array_merge($model->getOptions(), $options);

        if (isset($options['tools'])) {
            $options['tools'] = $this->contract->createToolOption($options['tools'], $model);
        }

        $requests = (function () use ($inputs, $model, $options): \Generator {
            foreach ($inputs as $input) {
                if (!$input instanceof BatchInput) {
                    throw new RuntimeException(\sprintf('A batch invocation expects "%s" instances as input, got "%s".', BatchInput::class, get_debug_type($input)));
                }

                yield [
                    'id' => $input->getId(),
                    'payload' => $this->contract->createRequestPayload($model, $input->getInput(), $options),
                ];
            }
        })();

        foreach ($this->batchSubmitClients as $batchSubmitClient) {
            if ($batchSubmitClient->supports($model)) {
                $rawResult = $batchSubmitClient->submitBatch($model, $requests, $options);

                foreach ($this->resultConverters as $resultConverter) {
                    if ($resultConverter instanceof BatchResultConverterInterface && $resultConverter->supports($model)) {
                        return new DeferredResult($resultConverter, $rawResult, $options);
                    }
                }

                throw new RuntimeException(\sprintf('No batch result converter registered for model "%s" (%s) in provider "%s".', $model->getName(), $model::class, $this->name));
            }
        }

        throw new RuntimeException(\sprintf('No batch submit client registered for model "%s" (%s) in provider "%s".', $model->getName(), $model::class, $this->name));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function convertResult(Model $model, RawResultInterface $result, array $options): DeferredResult
    {
        foreach ($this->resultConverters as $resultConverter) {
            // Batch converters only apply to the dedicated batch flow, never to a regular invocation.
            if ($resultConverter instanceof BatchResultConverterInterface) {
                continue;
            }

            if ($resultConverter->supports($model)) {
                return new DeferredResult($resultConverter, $result, $options);
            }
        }

        throw new RuntimeException(\sprintf('No ResultConverter registered for model "%s" (%s) in provider "%s".', $model->getName(), $model::class, $this->name));
    }
}
