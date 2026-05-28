<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Mock;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;

/**
 * Test model client that returns scripted responses and records every call.
 *
 * The scripted response can be:
 *  - a string: every call returns a {@see TextResult} wrapping it;
 *  - a map keyed by model name: per-model {@see ResultInterface} or string;
 *  - a \Closure(Model $model, array|string $payload, array $options): ResultInterface|string.
 *
 * The resolved {@see ResultInterface} is threaded through unchanged via the `object` slot of an
 * {@see InMemoryRawResult}, so every result type is supported without per-type conversion code.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class MockModelClient implements ModelClientInterface
{
    /**
     * @var list<array{model: Model, payload: array<string|int, mixed>|string, options: array<string, mixed>}>
     */
    private array $calls = [];

    /**
     * @param \Closure|string|array<string, ResultInterface|string> $responses
     */
    public function __construct(
        private readonly \Closure|string|array $responses,
    ) {
    }

    public function supports(Model $model): bool
    {
        return true;
    }

    /**
     * @param array<string|int, mixed> $payload
     * @param array<string, mixed>     $options
     */
    public function request(Model $model, array|string $payload, array $options = []): InMemoryRawResult
    {
        $this->calls[] = ['model' => $model, 'payload' => $payload, 'options' => $options];

        $result = $this->resolveResponse($model, $payload, $options);

        if (\is_string($result)) {
            $result = new TextResult($result);
        }

        return new InMemoryRawResult(object: $result);
    }

    /**
     * @return list<array{model: Model, payload: array<string|int, mixed>|string, options: array<string, mixed>}>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    /**
     * @param array<string|int, mixed>|string $payload
     * @param array<string, mixed>            $options
     */
    private function resolveResponse(Model $model, array|string $payload, array $options): ResultInterface|string
    {
        if ($this->responses instanceof \Closure) {
            return ($this->responses)($model, $payload, $options);
        }

        if (\is_string($this->responses)) {
            return $this->responses;
        }

        $name = $model->getName();

        if (!\array_key_exists($name, $this->responses)) {
            throw new InvalidArgumentException(\sprintf('No fake response configured for model "%s".', $name));
        }

        return $this->responses[$name];
    }
}
