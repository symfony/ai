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
 * Configuration for orchestrated multi-agent handoffs.
 *
 * @author Oskar Stark <oskar.stark@googlemail.com>
 */
final readonly class HandoffConfiguration
{
    /**
     * @param HandoffRule[] $rules
     */
    public function __construct(
        private array $rules = [],
        private ?string $delegationPrompt = null,
    ) {
    }

    /**
     * @return HandoffRule[]
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    public function getDelegationPrompt(): ?string
    {
        return $this->delegationPrompt;
    }


    /**
     * Find the first rule that should trigger for the given content.
     */
    public function findTriggeredRule(string $content): ?HandoffRule
    {
        foreach ($this->rules as $rule) {
            if ($rule->shouldTrigger($content)) {
                return $rule;
            }
        }

        return null;
    }


}