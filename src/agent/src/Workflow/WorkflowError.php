<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Workflow;

final class WorkflowError
{
    public function __construct(
        private readonly string $message,
        private readonly string $step,
        private readonly int $code = 0,
        private readonly ?\Throwable $previous = null,
        private readonly \DateTimeInterface $occurredAt = new \DateTimeImmutable(),
        private readonly array $context = [],
    ) {
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getStep(): string
    {
        return $this->step;
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function getPrevious(): ?\Throwable
    {
        return $this->previous;
    }

    public function getOccurredAt(): \DateTimeInterface
    {
        return $this->occurredAt;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'step' => $this->step,
            'code' => $this->code,
            'occurredAt' => $this->occurredAt->format(\DateTimeInterface::RFC3339),
            'context' => $this->context,
        ];
    }
}
