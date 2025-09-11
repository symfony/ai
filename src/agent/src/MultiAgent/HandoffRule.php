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

/**
 * Defines when and how to handoff execution to another agent.
 *
 * @author Oskar Stark <oskar.stark@googlemail.com>
 */
final readonly class HandoffRule
{
    /**
     * @param string[] $triggers Keywords or phrases that trigger this handoff rule
     */
    public function __construct(
        private string $agentName,
        private array $triggers = [],
    ) {
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


    /**
     * Check if this rule should trigger based on the given content.
     */
    public function shouldTrigger(string $content): bool
    {
        if (empty($this->triggers)) {
            return false;
        }

        foreach ($this->triggers as $trigger) {
            if (str_contains(strtolower($content), strtolower($trigger))) {
                return true;
            }
        }

        return false;
    }
}