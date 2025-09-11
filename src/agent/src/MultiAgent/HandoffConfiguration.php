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
        private int $maxHandoffs = 10,
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

    public function getMaxHandoffs(): int
    {
        return $this->maxHandoffs;
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

    /**
     * Create a new configuration with additional rules.
     *
     * @param HandoffRule[] $rules
     */
    public function withRules(array $rules): self
    {
        return new self([...$this->rules, ...$rules], $this->delegationPrompt, $this->maxHandoffs);
    }

    /**
     * Create a new configuration with a delegation prompt.
     */
    public function withDelegationPrompt(string $prompt): self
    {
        return new self($this->rules, $prompt, $this->maxHandoffs);
    }

    /**
     * Create a new configuration with a maximum handoff limit.
     */
    public function withMaxHandoffs(int $maxHandoffs): self
    {
        return new self($this->rules, $this->delegationPrompt, $maxHandoffs);
    }
}