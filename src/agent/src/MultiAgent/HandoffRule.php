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

use Symfony\AI\Agent\Exception\InvalidArgumentException;

/**
 * Defines when and how to handoff execution to another agent.
 *
 * @author Oskar Stark <oskar.stark@googlemail.com>
 */
final readonly class HandoffRule
{
    /**
     * @param string[] $triggers Keywords or phrases that indicate this handoff rule
     */
    public function __construct(
        private string $agentName,
        private array $triggers = [],
    ) {
        if (trim($agentName) === '') {
            throw new InvalidArgumentException('Agent name cannot be empty.');
        }
    }

    public function getAgentName(): string
    {
        return $this->agentName;
    }

    /**
     * @return string[]
     */
    public function getTriggers(): array
    {
        return $this->triggers;
    }
}