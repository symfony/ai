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

use Symfony\AI\Agent\Exception\ConflictException;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;

/**
 * Detects messages that were saved in the meantime, using the id of the last stored message as version.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ConflictDetector
{
    /**
     * @throws ConflictException if the messages don't start with the stored ones
     */
    public static function assertContinues(MessageBag $stored, MessageBag $messages): void
    {
        $storedIds = array_map(self::id(...), $stored->getMessages());
        $ids = array_map(self::id(...), \array_slice($messages->getMessages(), 0, \count($storedIds)));

        if ($storedIds !== $ids) {
            throw ConflictException::forMessages($messages);
        }
    }

    /**
     * @return string the id of the last message, or an empty string without messages
     */
    public static function version(MessageBag $messages): string
    {
        $all = $messages->getMessages();

        return [] === $all ? '' : self::id($all[array_key_last($all)]);
    }

    private static function id(MessageInterface $message): string
    {
        return $message->getId()->toRfc4122();
    }
}
