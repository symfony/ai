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
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Output
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly string $model,
        private ResultInterface $result,
        private readonly MessageBag $messageBag,
        private readonly array $options = [],
    ) {
    }

    public function getModel(): string
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

    public function getMessageBag(): MessageBag
    {
        return $this->messageBag;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
