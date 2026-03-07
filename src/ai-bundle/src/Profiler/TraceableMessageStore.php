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
use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 *
 * @phpstan-type MessageStoreData array{
 *      bag: MessageBag,
 *      saved_at: \DateTimeImmutable,
 *      identifier?: ?string,
 *  }
 */
final class TraceableMessageStore implements ManagedStoreInterface, MessageStoreInterface, ResetInterface
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

    public function save(MessageBag $messages, ?string $identifier = null): void
    {
        $this->calls[] = [
            'bag' => $messages,
            'saved_at' => $this->clock->now(),
            'identifier' => $identifier,
        ];

        $this->messageStore->save($messages, $identifier);
    }

    public function load(?string $identifier = null): MessageBag
    {
        return $this->messageStore->load($identifier);
    }

    public function drop(?string $identifier = null): void
    {
        if (!$this->messageStore instanceof ManagedStoreInterface) {
            return;
        }

        $this->messageStore->drop($identifier);
    }

    public function reset(): void
    {
        if ($this->messageStore instanceof ResetInterface) {
            $this->messageStore->reset();
        }
        $this->calls = [];
    }
}
