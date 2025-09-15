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

use Symfony\AI\Chat\MessageStoreInterface;
use Symfony\AI\Platform\Message\MessageBag;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 *
 * @phpstan-type MessageStoreData array<string, array{
 *      message: MessageBag,
 *      time: \DateTimeImmutable,
 *  }>
 */
final class TraceableMessageStore implements MessageStoreInterface
{
    /**
     * @var MessageStoreData[]
     */
    public array $messages = [];

    public function __construct(
        private readonly MessageStoreInterface $store,
    ) {
    }

    public function save(MessageBag $messages, ?string $id = null): void
    {
        $this->store->save($messages, $id);

        $this->messages[$id ?? $this->store->getId()][] = [
            'message' => $messages,
            'time' => new \DateTimeImmutable(),
        ];
    }

    public function load(?string $id = null): MessageBag
    {
        return $this->store->load($id);
    }

    public function clear(?string $id = null): void
    {
        $this->store->clear($id);
    }

    public function getId(): string
    {
        return $this->store->getId();
    }
}
