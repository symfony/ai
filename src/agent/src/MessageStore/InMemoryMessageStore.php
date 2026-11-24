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
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class InMemoryMessageStore implements MessageStoreInterface, ReplaceableMessageStoreInterface, ResetInterface
{
    /**
     * @var array<string, MessageBag>
     */
    private array $messages = [];

    /** @var array<string, array<string, true>> */
    private array $removedIds = [];

    public function resume(MessageBag $input): MessageBag
    {
        $this->assertNoRemovedMessages($input);

        return ConversationInput::appendNew($this->load($input->getId()), $input);
    }

    public function load(AbstractUid&TimeBasedUidInterface $id): MessageBag
    {
        return $this->messages[$id->toRfc4122()] ?? MessageBag::forId($id);
    }

    public function save(MessageBag $messages): void
    {
        $this->assertNoRemovedMessages($messages);
        ConflictDetector::assertContinues($this->load($messages->getId()), $messages);

        $this->messages[$messages->getId()->toRfc4122()] = $messages;
    }

    public function replace(MessageBag $expected, MessageBag $replacement): void
    {
        $stored = $this->load($expected->getId());
        ConflictDetector::assertContinues($stored, $expected);
        ConflictDetector::assertContinues($expected, $stored);
        $id = $expected->getId()->toRfc4122();
        foreach ($stored as $message) {
            $this->removedIds[$id][$message->getId()->toRfc4122()] = true;
        }
        foreach ($replacement as $message) {
            unset($this->removedIds[$id][$message->getId()->toRfc4122()]);
        }
        $this->messages[$id] = $replacement->withId($expected->getId());
    }

    public function delete(AbstractUid&TimeBasedUidInterface $id): void
    {
        unset($this->messages[$id->toRfc4122()], $this->removedIds[$id->toRfc4122()]);
    }

    public function reset(): void
    {
        $this->messages = [];
        $this->removedIds = [];
    }

    private function assertNoRemovedMessages(MessageBag $input): void
    {
        $removed = $this->removedIds[$input->getId()->toRfc4122()] ?? [];
        foreach ($input as $message) {
            if (isset($removed[$message->getId()->toRfc4122()])) {
                throw ConflictException::forMessages($input);
            }
        }
    }
}
