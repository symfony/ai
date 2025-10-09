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

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 *
 * @phpstan-type MessageStoreData array{
 *      bag: MessageBag,
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
    ) {
    }

    public function save(MessageBag $messages): void
    {
        $this->calls[] = [
            'bag' => $messages,
        ];

        $this->messageStore->save($messages);
    }

    public function load(): MessageBag
    {
        return $this->messageStore->load();
    }

    public function setup(array $options = []): void
    {
        if (!$this->messageStore instanceof ManagedStoreInterface) {
            return;
        }

        $this->messageStore->setup($options);
    }

    public function drop(): void
    {
        if (!$this->messageStore instanceof ManagedStoreInterface) {
            return;
        }

        $this->messageStore->drop();
    }
}
