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

use Symfony\AI\Agent\Context\Context;
use Symfony\AI\Agent\Execution\Cancellation;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 *
 * @phpstan-type AgentData array{
 *     input: string|MessageBag|UserMessage,
 *     context: Context,
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
        private readonly ?Stopwatch $stopwatch = null,
    ) {
    }

    public function call(string|MessageBag|UserMessage $input, Context $context = new Context(), array $options = []): Execution
    {
        $this->calls[] = [
            'input' => $input,
            'context' => $context,
            'options' => $options,
            'called_at' => $this->clock->now(),
        ];

        $execution = $this->agent->call($input, $context, $options);

        if (null === $this->stopwatch) {
            return $execution;
        }

        $stopwatch = $this->stopwatch;
        $eventName = \sprintf('ai.agent.call "%s"', $this->agent->getName());
        $cancellation = new Cancellation();

        // The agent only runs while its execution is consumed, so the event spans the consumption
        return new Execution(static function () use ($execution, $stopwatch, $eventName, $cancellation): \Generator {
            $event = $stopwatch->start($eventName, 'ai');

            try {
                yield from $cancellation->forward($execution);
            } finally {
                // The profiler may have already closed the event when the execution is consumed late
                if ($event->isStarted()) {
                    $event->stop();
                }
            }
        }, $execution->isStreamed(), $cancellation);
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
