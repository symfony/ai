<?php

namespace Symfony\AI\Platform\Connector;

use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\ResultInterface as ConverterResult;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface ConnectorInterface
{
    public function getContract(): Contract;

    /**
     * @param array<int|string, mixed>|string $payload
     * @param array<string, mixed>            $options
     *
     * @return array<string, mixed>
     */
    public function call(Model $model, array|string $payload, array $options): ResultPromise;

    public function isError(ResultInterface $result): bool;

    public function handleStream(Model $model, ResultInterface $result, array $options): StreamResult;

    /**
     * @throws ConnectorException
     */
    public function handleError(Model $model, ResultInterface $result): never;

    public function handleResult(Model $model, ResultInterface $result, array $options): ConverterResult;
}
