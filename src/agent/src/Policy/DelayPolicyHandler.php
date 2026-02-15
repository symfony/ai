<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Policy;

use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class DelayPolicyHandler implements PolicyHandlerInterface
{
    public function __construct(
        private readonly ClockInterface $clock = new MonotonicClock(),
    ) {
    }

    /**
     * @param InputDelayPolicy|OutputDelayPolicy $policy
     */
    public function handle(MessageBag $messages, array $options, InputPolicyInterface|OutputPolicyInterface $policy): void
    {
        $this->clock->sleep($policy->getDelay());
    }

    public function support(InputPolicyInterface|OutputPolicyInterface $policy): bool
    {
        return $policy instanceof InputDelayPolicy || $policy instanceof OutputDelayPolicy;
    }
}
