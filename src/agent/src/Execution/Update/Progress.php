<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Execution\Update;

use Symfony\AI\Agent\Execution\UpdateInterface;
use Symfony\AI\Agent\Execution\UpdateType;

/**
 * A non-blocking, informational update about the agent's progress.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Progress implements UpdateInterface
{
    /**
     * @param non-empty-string $stage   machine-readable stage, e.g. "model_request", "tool_call", "delta"
     * @param string           $message human-readable description
     * @param mixed            $payload stage-specific payload (e.g. the ToolCall, a streamed delta, ...)
     */
    public function __construct(
        private readonly string $stage,
        private readonly string $message = '',
        private readonly mixed $payload = null,
    ) {
    }

    public function getType(): UpdateType
    {
        return UpdateType::Progress;
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
