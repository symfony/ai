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
use Symfony\AI\Platform\ProviderInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched when converting the deferred result failed.
 *
 * Dispatched lazily once the conversion of the raw result throws, so listeners can
 * observe the failure (e.g. to release resources). The underlying exception is still
 * rethrown to the caller afterwards.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ResultErrorEvent extends Event
{
    /**
     * @param array<string, mixed>               $options
     * @param array<string, mixed>|string|object $input
     */
    public function __construct(
        private readonly Model $model,
        private readonly \Throwable $error,
        private readonly array $options = [],
        private readonly array|string|object $input = [],
        private readonly ?ProviderInterface $provider = null,
    ) {
    }

    public function getProvider(): ?ProviderInterface
    {
        return $this->provider;
    }

    public function getModel(): Model
    {
        return $this->model;
    }

    public function getError(): \Throwable
    {
        return $this->error;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @return array<string, mixed>|string|object
     */
    public function getInput(): array|string|object
    {
        return $this->input;
    }
}
