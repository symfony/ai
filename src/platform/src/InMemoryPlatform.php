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

use Symfony\AI\Platform\ModelCatalog\DynamicModelCatalog;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\ResultPromise;
use Symfony\AI\Platform\Result\TextResult;

/**
 * A fake implementation of PlatformInterface that returns fixed or callable responses.
 *
 * Useful for unit or integration testing without real API calls.
 *
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
class InMemoryPlatform implements PlatformInterface
{
    private readonly ModelCatalogInterface $modelCatalog;

    /**
     * The mock result can be a string or a callable that returns a string.
     * If it's a closure, it receives the model, input, and optionally options as parameters like a real platform call.
     */
    public function __construct(private readonly \Closure|string $mockResult)
    {
        $this->modelCatalog = new DynamicModelCatalog();
    }

    public function invoke(string $model, array|string|object $input, array $options = []): ResultPromise
    {
        $model = new class($model) extends Model {
            public function __construct(string $name)
            {
                parent::__construct($name);
            }
        };
        $result = \is_string($this->mockResult) ? $this->mockResult : ($this->mockResult)($model, $input, $options);

        if ($result instanceof ResultInterface) {
            return $this->createPromise($result, $options);
        }

        return $this->createPromise(new TextResult($result), $options);
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        return $this->modelCatalog;
    }

    /**
     * Creates a ResultPromise from a ResultInterface.
     *
     * @param ResultInterface      $result  The result to wrap in a promise
     * @param array<string, mixed> $options Additional options for the promise
     */
    private function createPromise(ResultInterface $result, array $options): ResultPromise
    {
        $rawResult = $result->getRawResult() ?? new InMemoryRawResult(
            ['text' => $result->getContent()],
            (object) ['text' => $result->getContent()],
        );

        return new ResultPromise(static fn () => $result, $rawResult, $options);
    }
}
