<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Execution;

use Symfony\AI\Agent\Execution\Update\Progress;

/**
 * A numbered {@see Progress} update, to poll an execution with a cursor.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ExecutionEvent
{
    /**
     * @param positive-int     $sequence
     * @param non-empty-string $stage
     */
    public function __construct(
        private readonly int $sequence,
        private readonly string $stage,
        private readonly string $message = '',
        private readonly mixed $payload = null,
    ) {
    }

    public static function fromProgress(int $sequence, Progress $progress): self
    {
        return new self($sequence, $progress->getStage(), $progress->getMessage(), $progress->getPayload());
    }

    /**
     * @return positive-int
     */
    public function getSequence(): int
    {
        return $this->sequence;
    }

    /**
     * @return non-empty-string
     */
    public function getStage(): string
    {
        return $this->stage;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getPayload(): mixed
    {
        return $this->payload;
    }
}
