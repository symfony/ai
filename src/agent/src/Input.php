<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent;

use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Input
{
    /**
     * @param non-empty-string|Model $model
     * @param array<string, mixed>   $options
     */
    public function __construct(
        private string|Model $model,
        private MessageBag $messageBag,
        private array $options = [],
    ) {
    }

    /**
     * @return non-empty-string|Model
     */
    public function getModel(): string|Model
    {
        return $this->model;
    }

    /**
     * @param non-empty-string|Model $model
     */
    public function setModel(string|Model $model): void
    {
        $this->model = $model;
    }

    public function getMessageBag(): MessageBag
    {
        return $this->messageBag;
    }

    public function setMessageBag(MessageBag $messageBag): void
    {
        $this->messageBag = $messageBag;
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
