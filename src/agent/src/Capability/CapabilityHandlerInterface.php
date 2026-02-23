<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Capability;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\MessageBag;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
interface CapabilityHandlerInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function handle(AgentInterface $agent, MessageBag $messages, array $options, InputCapabilityInterface|OutputCapabilityInterface $capability): void;

    public function support(InputCapabilityInterface|OutputCapabilityInterface $capability): bool;
}
