<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Profiler;

use Symfony\AI\Chat\ManagedStoreInterface;
use Symfony\AI\Chat\MessageStoreInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Clock\ClockInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 *
 * @phpstan-type MessageStoreData array{
 *      bag: MessageBag,
 *      saved_at: \DateTimeImmutable,
 *  }
 */
final class TraceableMessageStore implements ManagedStoreInterface, MessageStoreInterface
{
    /**
     * @var MessageStoreData[]
     */
    public array $calls = [];

    public function __construct(
        private readonly MessageStoreInterface|ManagedStoreInterface $messageStore,
        private readonly ClockInterface $clock,
    ) {
    }

    public function setup(array $options = []): void
    {
        if (!$this->messageStore instanceof ManagedStoreInterface) {
            return;
        }

        $this->messageStore->setup($options);
    }

    public function save(MessageBag $messages): void
    {
        $this->calls[] = [
            'bag' => $messages,
            'saved_at' => $this->clock->now(),
        ];

        $this->messageStore->save($messages);
    }

    public function load(): MessageBag
    {
        return $this->messageStore->load();
    }

    public function drop(): void
    {
        if (!$this->messageStore instanceof ManagedStoreInterface) {
            return;
        }

        $this->messageStore->drop();
    }
}
