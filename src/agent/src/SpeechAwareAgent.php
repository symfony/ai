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

use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class SpeechAwareAgent implements AgentInterface
{
    public function __construct(
        private readonly AgentInterface $agent,
        private readonly PlatformInterface $platform,
    ) {
    }

    public function call(MessageBag $messages, array $options = []): ResultInterface
    {
        $output = $this->agent->call($messages, $options);
    }

    public function getName(): string
    {
        return $this->agent->getName();
    }
}
