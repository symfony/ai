<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Session;

use Symfony\AI\Agent\MessageStore\ConflictDetector;
use Symfony\AI\Agent\MessageStore\MessageStoreInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;

/**
 * Keeps the conversations of the current user in their session.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class MessageStore implements MessageStoreInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly string $sessionKey = 'messages',
    ) {
    }

    public function load(AbstractUid&TimeBasedUidInterface $id): MessageBag
    {
        return $this->all()[$id->toRfc4122()] ?? MessageBag::forId($id);
    }

    public function save(MessageBag $messages): void
    {
        ConflictDetector::assertContinues($this->load($messages->getId()), $messages);

        $this->requestStack->getSession()->set($this->sessionKey, [
            ...$this->all(),
            $messages->getId()->toRfc4122() => $messages,
        ]);
    }

    public function delete(AbstractUid&TimeBasedUidInterface $id): void
    {
        $messages = $this->all();
        unset($messages[$id->toRfc4122()]);

        $this->requestStack->getSession()->set($this->sessionKey, $messages);
    }

    /**
     * @return array<string, MessageBag>
     */
    private function all(): array
    {
        return $this->requestStack->getSession()->get($this->sessionKey, []);
    }
}
