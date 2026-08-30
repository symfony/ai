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
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched after the deferred result has been converted into the actual result.
 *
 * Unlike ResultEvent, which fires when the deferred result object is created, this event
 * is dispatched lazily once the raw result has actually been converted, so listeners can
 * act on the resolved result.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ResultConvertedEvent extends Event
{
    /**
     * @param array<string, mixed>               $options
     * @param array<string, mixed>|string|object $input
     */
    public function __construct(
        private readonly Model $model,
        private ResultInterface $result,
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

    public function getResult(): ResultInterface
    {
        return $this->result;
    }

    public function setResult(ResultInterface $result): void
    {
        $this->result = $result;
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
