<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Context;

use Symfony\AI\Agent\AgentInterface;

/**
 * Handle given to a {@see ContextProcessorInterface} during a run, exposing the running agent.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class AgentContext
{
    public function __construct(
        private readonly AgentInterface $agent,
    ) {
    }

    public function getAgent(): AgentInterface
    {
        return $this->agent;
    }
}
