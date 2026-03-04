<?php

namespace Symfony\AI\Platform;

use Symfony\AI\Platform\Connector\ConnectorInterface;
use Symfony\AI\Platform\Connector\ResultInterface as RawResultInterface;
use Symfony\AI\Platform\Connector\ResultPromise;
use Symfony\AI\Platform\Result\ResultInterface;

final readonly class ConnectorPlatform
{
    public function __construct(
        private ConnectorInterface $connector,
    ) {
    }

    public function call(Model $model, object|array|string $input, array $options = []): ResultPromise
    {
        $contract = $this->connector->getContract();
        $payload = $contract->createRequestPayload($model, $input);
        $options = array_merge($model->getOptions(), $options);

        if (isset($options['tools'])) {
            $options['tools'] = $contract->createToolOption($options['tools'], $model);
        }

        $options['model'] = $model;

        $promise = $this->connector->call($model, $payload, $options);
        $promise->registerConverter($this->convertResult(...));

        return $promise;
    }

    private function convertResult(RawResultInterface $result, array $options): ResultInterface
    {
        if ($options['stream'] ?? false) {
            return $this->connector->handleStream($options['model'], $result, $options);
        }

        if ($this->connector->isError($result)) {
            $this->connector->handleError($options['model'], $result);
        }

        return $this->connector->handleResult($options['model'], $result, $options);
    }
}
