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
 * Configuration for multi-agent handoffs.
 *
 * @author Oskar Stark <oskar.stark@googlemail.com>
 */
final readonly class HandoffConfig
{
    /**
     * @param HandoffRule[] $rules
     */
    public function __construct(
        private string $delegationPrompt,
        private array $rules,
    ) {
    }

    /**
     * @return HandoffRule[]
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    public function getDelegationPrompt(): string
    {
        return $this->delegationPrompt;
    }
}