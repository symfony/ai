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

/**
 * Defines a handoff to another agent based on conditions.
 *
 * @author Oskar Stark <oskar.stark@googlemail.com>
 */
final readonly class Handoff
{
    /**
     * @param string[] $when Keywords or phrases that indicate this handoff
     */
    public function __construct(
        private AgentInterface $to,
        private array $when = [],
    ) {
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
