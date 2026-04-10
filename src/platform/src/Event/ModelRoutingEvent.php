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

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched by Platform before resolving a model to a provider.
 *
 * Allows listeners to observe or modify the model name, input, and options
 * before routing takes place.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ModelRoutingEvent extends Event
{
    /**
     * @param non-empty-string           $model
     * @param array<mixed>|string|object $input
     * @param array<string, mixed>       $options
     */
    public function __construct(
        private string $model,
        private array|string|object $input,
        private array $options = [],
    ) {
    }

    /**
     * @return non-empty-string
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * @param non-empty-string $model
     */
    public function setModel(string $model): void
    {
        $this->model = $model;
    }

    /**
     * @return array<mixed>|string|object
     */
    public function getInput(): array|string|object
    {
        return $this->input;
    }

    /**
     * @param array<mixed>|string|object $input
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
