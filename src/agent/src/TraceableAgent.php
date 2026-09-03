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

use Symfony\AI\Agent\Approval\ApprovalDecision;
use Symfony\AI\Agent\Approval\Checkpoint\ExecutionCheckpoint;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 * @author Saiful Islam <saif012@gmail.com>
 *
 * @phpstan-type AgentData array{
 *     input: string|MessageBag|UserMessage|ExecutionCheckpoint,
 *     options: array<string, mixed>,
 *     called_at: \DateTimeImmutable,
 * }
 */
final class TraceableAgent implements AgentInterface, ResetInterface
{
    /**
     * @var AgentData[]
     */
    private array $calls = [];

    public function __construct(
        private readonly AgentInterface $agent,
        private readonly ClockInterface $clock = new MonotonicClock(),
    ) {
    }

    public function call(string|MessageBag|UserMessage $input, array $options = []): Execution
    {
        $this->calls[] = [
            'input' => $input,
            'options' => $options,
            'called_at' => $this->clock->now(),
        ];

        return $this->agent->call($input, $options);
    }

    public function resume(ExecutionCheckpoint|string $checkpoint, ApprovalDecision $decision): ResultInterface
    {
        $this->calls[] = [
            'input' => $checkpoint,
            'options' => ['decision' => $decision],
            'called_at' => $this->clock->now(),
        ];

        return $this->agent->resume($checkpoint, $decision);
    }

    public function getName(): string
    {
        return $this->agent->getName();
    }

    /**
     * @return AgentData[]
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    public function reset(): void
    {
        $this->calls = [];
    }
}
