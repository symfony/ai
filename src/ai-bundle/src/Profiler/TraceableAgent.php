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
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 *
 * @phpstan-type AgentData array{
 *     messages: MessageBag,
 *     options: array<string, mixed>,
 *     called_at: \DateTimeImmutable,
 * }
 */
final class TraceableAgent implements AgentInterface, ResetInterface
{
    /**
     * @var AgentData[]
     */
    public array $calls = [];

    public function __construct(
        private readonly AgentInterface $agent,
        private readonly ClockInterface $clock = new MonotonicClock(),
    ) {
    }

    public function call(MessageBag $messages, array $options = []): ResultInterface
    {
        $this->calls[] = [
            'messages' => $messages,
            'options' => $options,
            'called_at' => $this->clock->now(),
        ];

        return $this->agent->call($messages, $options);
    }

    public function getName(): string
    {
        return $this->agent->getName();
    }

    public function reset(): void
    {
        $this->calls = [];
    }
}
