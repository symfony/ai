<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Tool;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\TextResult;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Agent
{
    public function __construct(
        private readonly AgentInterface $agent,
    ) {
    }

    /**
     * @param string $message the message to pass to the chain
     */
    public function __invoke(string $message): string
    {
        $result = $this->agent->call(new MessageBag(Message::ofUser($message)));

        \assert($result instanceof TextResult);

        return $result->getContent();
    }
}
