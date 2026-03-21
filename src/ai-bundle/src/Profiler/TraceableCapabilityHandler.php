<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Profiler;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Capability\CapabilityHandlerInterface;
use Symfony\AI\Agent\Capability\InputCapabilityInterface;
use Symfony\AI\Agent\Capability\OutputCapabilityInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 *
 * @phpstan-type CapabilityHandlerData array{
 *    method: string,
 *    agent: AgentInterface,
 *    messages?: MessageBag,
 *    options?: array<string, mixed>,
 *    capability: string,
 *    handled_at?: \DateTimeImmutable,
 *    checked_at?: \DateTimeImmutable,
 * }
 */
final class TraceableCapabilityHandler implements CapabilityHandlerInterface, ResetInterface
{
    /**
     * @var CapabilityHandlerData[]
     */
    public array $calls = [];

    public function __construct(
        private readonly CapabilityHandlerInterface $policyHandler,
        private readonly ClockInterface $clock = new MonotonicClock(),
    ) {
    }

    public function handle(AgentInterface $agent, MessageBag $messages, array $options, InputCapabilityInterface|OutputCapabilityInterface $capability): void
    {
        $this->calls[] = [
            'method' => 'handle',
            'agent' => $agent,
            'messages' => $messages,
            'options' => $options,
            'capability' => $capability::class,
            'handled_at' => $this->clock->now(),
        ];

        $this->policyHandler->handle($agent, $messages, $options, $capability);
    }

    public function support(InputCapabilityInterface|OutputCapabilityInterface $capability): bool
    {
        $this->calls[] = [
            'method' => 'support',
            'capability' => $capability::class,
            'checked_at' => $this->clock->now(),
        ];

        return $this->policyHandler->support($capability);
    }

    public function reset(): void
    {
        $this->calls = [];
    }
}
