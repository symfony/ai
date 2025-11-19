<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Test;

use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\FallbackModelCatalog;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\ResultInterface;
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
        $this->modelCatalog = new FallbackModelCatalog();
    }

    public function invoke(string $model, array|string|object $input, array $options = []): DeferredResult
    {
        $model = new class($model) extends Model {
            public function __construct(string $name)
            {
                parent::__construct($name);
            }
        };
        $result = \is_string($this->mockResult) ? $this->mockResult : ($this->mockResult)($model, $input, $options);

        if ($result instanceof ResultInterface) {
            return $this->createDeferredResult($result, $options);
        }

        return $this->createDeferredResult(new TextResult($result), $options);
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
    private function createDeferredResult(ResultInterface $result, array $options): DeferredResult
    {
        $rawResult = $result->getRawResult() ?? new InMemoryRawResult(
            ['text' => $result->getContent()],
            [],
            (object) ['text' => $result->getContent()],
        );

        return new DeferredResult(new PlainConverter($result), $rawResult, $options);
    }
}
