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
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class InMemoryMessageStore implements MessageStoreInterface, ResetInterface
{
    /**
     * @var array<string, MessageBag>
     */
    private array $messages = [];

    public function load(AbstractUid&TimeBasedUidInterface $id): MessageBag
    {
        return $this->messages[$id->toRfc4122()] ?? MessageBag::forId($id);
    }

    public function save(MessageBag $messages): void
    {
        ConflictDetector::assertContinues($this->load($messages->getId()), $messages);

        $this->messages[$messages->getId()->toRfc4122()] = $messages;
    }

    public function delete(AbstractUid&TimeBasedUidInterface $id): void
    {
        unset($this->messages[$id->toRfc4122()]);
    }

    public function reset(): void
    {
        $this->messages = [];
    }
}
