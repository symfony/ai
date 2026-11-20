<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\MessageStore;

use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;

/**
 * @author Illia Vasylevskyi <ineersa@gmail.com>
 *
 * @internal
 */
final class ConversationInput
{
    public static function appendNew(MessageBag $stored, MessageBag $input): MessageBag
    {
        $storedIds = array_flip(array_map(static fn (MessageInterface $message): string => $message->getId()->toRfc4122(), $stored->getMessages()));
        $messages = $stored->merge(new MessageBag(...array_filter(
            $input->getMessages(),
            static fn (MessageInterface $message): bool => !isset($storedIds[$message->getId()->toRfc4122()]),
        )));
        $messages->getMetadata()->set(array_replace($stored->getMetadata()->all(), $input->getMetadata()->all()));

        return $messages;
    }
}
