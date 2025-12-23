<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Event;

use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched after platform created the deferred result object for input.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ResultEvent extends Event
{
    /**
     * @param array<string, mixed>               $options
     * @param array<string, mixed>|string|object $input
     */
    public function __construct(
        private Model $model,
        private DeferredResult $deferredResult,
        private array $options = [],
        private array|string|object $input = [],
    ) {
    }

    public function getModel(): Model
    {
        return $this->model;
    }

    public function setModel(Model $model): void
    {
        $this->model = $model;
    }

    public function getDeferredResult(): DeferredResult
    {
        return $this->deferredResult;
    }

    public function setDeferredResult(DeferredResult $deferredResult): void
    {
        $this->deferredResult = $deferredResult;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    /**
     * @return array<string, mixed>|string|object
     */
    public function getInput(): array|string|object
    {
        return $this->input;
    }
}
