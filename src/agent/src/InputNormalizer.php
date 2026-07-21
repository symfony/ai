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

use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;

/**
 * Normalizes the different input shapes accepted by {@see AgentInterface::call()}.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @internal
 */
final class InputNormalizer
{
    public static function toMessageBag(string|MessageBag|UserMessage $input): MessageBag
    {
        if ($input instanceof MessageBag) {
            return $input;
        }

        if ($input instanceof UserMessage) {
            return new MessageBag($input);
        }

        return new MessageBag(Message::ofUser($input));
    }
}
