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

use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\AI\Platform\Event\InvocationEvent;
use Symfony\AI\Platform\Event\ResultEvent;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawResultInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Platform implements PlatformInterface
{
    /**
     * @var ModelClientInterface[]
     */
    private readonly array $modelClients;

    /**
     * @var ResultConverterInterface[]
     */
    private readonly array $resultConverters;

    /**
     * @param iterable<ModelClientInterface>     $modelClients
     * @param iterable<ResultConverterInterface> $resultConverters
     */
    public function __construct(
        iterable $modelClients,
        iterable $resultConverters,
        private readonly ModelCatalogInterface $modelCatalog,
        private ?Contract $contract = null,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        $this->contract = $contract ?? Contract::create();
        $this->modelClients = $modelClients instanceof \Traversable ? iterator_to_array($modelClients) : $modelClients;
        $this->resultConverters = $resultConverters instanceof \Traversable ? iterator_to_array($resultConverters) : $resultConverters;
    }

    public function invoke(string $model, array|string|object $input, array $options = []): DeferredResult
    {
        $model = $this->modelCatalog->getModel($model);

        $event = new InvocationEvent($model, $input, $options);
        $this->eventDispatcher?->dispatch($event);

        $payload = $this->contract->createRequestPayload($event->getModel(), $event->getInput());
        $options = array_merge($model->getOptions(), $event->getOptions());

        if (isset($options['tools'])) {
            $options['tools'] = $this->contract->createToolOption($options['tools'], $model);
        }

        $result = $this->convertResult($model, $this->doInvoke($model, $payload, $options), $options);

        $event = new ResultEvent($model, $result, $options);
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

        throw new RuntimeException(\sprintf('No ModelClient registered for model "%s" with given input.', $model::class));
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

        throw new RuntimeException(\sprintf('No ResultConverter registered for model "%s" with given input.', $model::class));
    }
}
