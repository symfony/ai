<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Conversation;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\UuidV7;

/**
 * Remembers the ID of the current conversation of each chat in the session.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ConversationIds
{
    private const SESSION_PREFIX = 'conversation-';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function get(string $chat): UuidV7
    {
        $session = $this->requestStack->getSession();

        if (!$session->has(self::SESSION_PREFIX.$chat)) {
            $session->set(self::SESSION_PREFIX.$chat, (new UuidV7())->toRfc4122());
        }

        return UuidV7::fromString($session->get(self::SESSION_PREFIX.$chat));
    }

    public function reset(string $chat): void
    {
        $this->requestStack->getSession()->remove(self::SESSION_PREFIX.$chat);
    }
}
