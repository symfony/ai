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

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultPromise;

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
        private ?Contract $contract = null,
    ) {
        $this->contract = $contract ?? Contract::create();
        $this->modelClients = $modelClients instanceof \Traversable ? iterator_to_array($modelClients) : $modelClients;
        $this->resultConverters = $resultConverters instanceof \Traversable ? iterator_to_array($resultConverters) : $resultConverters;
    }

    public function invoke(Model $model, array|string|object $input, array $options = [], Action $action = Action::CHAT): ResultPromise
    {
        $payload = $this->contract->createRequestPayload($model, $input);
        $options = array_merge($model->getOptions(), $options);

        if (isset($options['tools'])) {
            $options['tools'] = $this->contract->createToolOption($options['tools'], $model);
        }

        $result = $this->doInvoke($model, $action, $payload, $options);

        return $this->convertResult($model, $action, $result, $options);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    private function doInvoke(Model $model, Action $action, array|string $payload, array $options = []): RawResultInterface
    {
        foreach ($this->modelClients as $modelClient) {
            if ($modelClient->supports($model, $action)) {
                return $modelClient->request($model, $action, $payload, $options);
            }
        }

        throw new RuntimeException(\sprintf('No ModelClient registered for model "%s" with given input.', $model::class));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function convertResult(Model $model, Action $action, RawResultInterface $result, array $options): ResultPromise
    {
        foreach ($this->resultConverters as $resultConverter) {
            if ($resultConverter->supports($model, $action)) {
                return new ResultPromise($resultConverter->convert(...), $result, $options);
            }
        }

        throw new RuntimeException(\sprintf('No ResultConverter registered for model "%s" with given input.', $model::class));
    }
}
