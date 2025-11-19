<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\MultiAgent;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Exception\InvalidArgumentException;

/**
 * Defines a handoff to another agent based on conditions.
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class Handoff
{
    /**
     * @param string[] $when Keywords or phrases that indicate this handoff
     */
    public function __construct(
        private readonly AgentInterface $to,
        private readonly array $when,
    ) {
        if ([] === $when) {
            throw new InvalidArgumentException('Handoff must have at least one "when" condition.');
        }
    }

    public function getTo(): AgentInterface
    {
        return $this->to;
    }

    /**
     * @return string[]
     */
    public function getWhen(): array
    {
        return $this->when;
    }
}
