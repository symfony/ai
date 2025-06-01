<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
trait AgentAwareTrait
{
    private AgentInterface $agent;

    public function setAgent(AgentInterface $agent): void
    {
        $this->agent = $agent;
    }
}
