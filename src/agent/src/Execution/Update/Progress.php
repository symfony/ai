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
     * The model is about to be invoked; the payload is the model name.
     */
    public const STAGE_MODEL_REQUEST = 'model_request';

    /**
     * A streamed delta was received; the payload is the {@see \Symfony\AI\Platform\Result\Stream\Delta\DeltaInterface}.
     */
    public const STAGE_DELTA = 'delta';

    /**
     * A tool call is about to be executed; the payload is the {@see \Symfony\AI\Platform\Result\ToolCall}.
     */
    public const STAGE_TOOL_CALL = 'tool_call';

    /**
     * A {@see \Symfony\AI\Agent\MultiAgent\MultiAgent} routed the input to another agent; the payload is its
     * {@see \Symfony\AI\Agent\MultiAgent\Handoff\Decision}.
     */
    public const STAGE_HANDOFF = 'handoff';

    /**
     * @param non-empty-string $stage   machine-readable stage, one of the STAGE_* constants for a stage this
     *                                  package itself reports, or any other string a decorator or a custom
     *                                  agent chooses for its own
     * @param string           $message human-readable description
     * @param mixed            $payload stage-specific payload (e.g. the ToolCall, a streamed delta, the handoff Decision, ...)
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
