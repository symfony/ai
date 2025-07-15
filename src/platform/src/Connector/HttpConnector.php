<?php

namespace Symfony\AI\Platform\Connector;

use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Model;
use Symfony\Component\HttpClient\EventSourceHttpClient;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
abstract class HttpConnector implements ConnectorInterface
{
    public function getContract(): Contract
    {
        return Contract::create();
    }

    public function call(Model $model, array|string $payload, array $options): ResultPromise
    {
        $response = $this->initHttpClient()->request('POST', $this->getEndpoint($model), [
            'json' => $payload,
        ]);

        return new ResultPromise(new HttpResult($response), $options);
    }

    abstract protected function initHttpClient(): EventSourceHttpClient;

    abstract protected function getEndpoint(Model $model): string;
}
