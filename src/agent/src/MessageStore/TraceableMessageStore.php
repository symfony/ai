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
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 *
 * @phpstan-type MessageStoreData array{
 *      bag: MessageBag,
 *      saved_at: \DateTimeImmutable,
 *  }
 */
final class TraceableMessageStore implements ManagedMessageStoreInterface, MessageStoreInterface, ResetInterface
{
    /**
     * @var MessageStoreData[]
     */
    private array $calls = [];

    public function __construct(
        private readonly MessageStoreInterface $messageStore,
        private readonly ClockInterface $clock,
    ) {
    }

    public function setup(array $options = []): void
    {
        if ($this->messageStore instanceof ManagedMessageStoreInterface) {
            $this->messageStore->setup($options);
        }
    }

    public function load(AbstractUid&TimeBasedUidInterface $id): MessageBag
    {
        return $this->messageStore->load($id);
    }

    public function save(MessageBag $messages): void
    {
        $this->messageStore->save($messages);

        $this->calls[] = [
            'bag' => $messages,
            'saved_at' => $this->clock->now(),
        ];
    }

    public function delete(AbstractUid&TimeBasedUidInterface $id): void
    {
        $this->messageStore->delete($id);
    }

    public function drop(): void
    {
        if ($this->messageStore instanceof ManagedMessageStoreInterface) {
            $this->messageStore->drop();
        }
    }

    /**
     * @return MessageStoreData[]
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    public function reset(): void
    {
        if ($this->messageStore instanceof ResetInterface) {
            $this->messageStore->reset();
        }

        $this->calls = [];
    }
}
