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
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched before platform invocation to allow modification of input data.
 *
 * @author Ramy Hakam <pencilsoft1@gmail.com>
 */
final class InvocationEvent extends Event
{
    /**
     * @param array<string, mixed>|string|object $input
     * @param array<string, mixed>               $options
     */
    public function __construct(
        private Model $model,
        private array|string|object $input,
        private array $options = [],
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

    /**
     * @return array<string, mixed>|string|object
     */
    public function getInput(): array|string|object
    {
        return $this->input;
    }

    /**
     * @param array<string, mixed>|string|object $input
     */
    public function setInput(array|string|object $input): void
    {
        $this->input = $input;
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
}
