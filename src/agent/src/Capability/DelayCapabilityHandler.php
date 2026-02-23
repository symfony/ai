<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Capability;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class DelayCapabilityHandler implements CapabilityHandlerInterface
{
    public function __construct(
        private readonly ClockInterface $clock = new MonotonicClock(),
    ) {
    }

    /**
     * @param InputDelayCapability|OutputDelayCapability $capability
     */
    public function handle(AgentInterface $agent, MessageBag $messages, array $options, InputCapabilityInterface|OutputCapabilityInterface $capability): void
    {
        $this->clock->sleep($capability->getDelay());
    }

    public function support(InputCapabilityInterface|OutputCapabilityInterface $capability): bool
    {
        return $capability instanceof InputDelayCapability || $capability instanceof OutputDelayCapability;
    }
}
