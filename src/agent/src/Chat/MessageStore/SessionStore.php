<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Chat\MessageStore;

use Symfony\AI\Agent\Chat\MessageStoreInterface;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class SessionStore implements MessageStoreInterface
{
    private SessionInterface $session;

    public function __construct(
        RequestStack $requestStack,
        private string $id = '_message_store_session',
    ) {
        if (!class_exists(RequestStack::class)) {
            throw new RuntimeException('For using the SessionStore as message store, the symfony/http-foundation package is required. Try running "composer require symfony/http-foundation".');
        }

        $this->session = $requestStack->getSession();
    }

    public function save(MessageBag $messages, ?string $id = null): void
    {
        $this->session->set($id ?? $this->id, $messages);
    }

    public function load(?string $id = null): MessageBag
    {
        return $this->session->get($id ?? $this->id, new MessageBag());
    }

    public function clear(?string $id = null): void
    {
        $this->session->remove($id ?? $this->id);
    }

    public function getId(): string
    {
        return $this->id;
    }
}
