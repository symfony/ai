<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Context;

use Symfony\AI\Platform\Message\MessageBag;

/**
 * Mutable request envelope passed through the context processor chain before
 * the platform is invoked. Replaces the former Input class.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class AgentRequest
{
    /**
     * @param non-empty-string     $model
     * @param array<string, mixed> $options
     */
    public function __construct(
        private string $model,
        private MessageBag $messageBag,
        private array $options,
        private Context $context,
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

    public function getContext(): Context
    {
        return $this->context;
    }

    public function setContext(Context $context): void
    {
        $this->context = $context;
    }
}
