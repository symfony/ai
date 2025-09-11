<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\MultiAgent\StructuredOutput;

/**
 * Represents the result of agent selection by the orchestrator.
 *
 * @author Oskar Stark <oskar.stark@googlemail.com>
 */
final readonly class AgentSelection
{
    public function __construct(
        public string $agentName,
        public string $reasoning,
    ) {
    }
}