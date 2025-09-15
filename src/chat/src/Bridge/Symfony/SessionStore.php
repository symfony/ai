<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat\Bridge\Symfony;

use Symfony\AI\Chat\Exception\RuntimeException;
use Symfony\AI\Chat\MessageStoreIdentifierTrait;
use Symfony\AI\Chat\MessageStoreInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class SessionStore implements MessageStoreInterface
{
    use MessageStoreIdentifierTrait;

    private SessionInterface $session;

    public function __construct(
        RequestStack $requestStack,
        string $id = '_message_store_session',
    ) {
        if (!class_exists(RequestStack::class)) {
            throw new RuntimeException('For using the SessionStore as message store, the symfony/http-foundation package is required. Try running "composer require symfony/http-foundation".');
        }

        $this->session = $requestStack->getSession();

        $this->setId($id);
    }

    public function save(MessageBag $messages, ?string $id = null): void
    {
        $this->session->set($id ?? $this->getId(), $messages);
    }

    public function load(?string $id = null): MessageBag
    {
        return $this->session->get($id ?? $this->getId(), new MessageBag());
    }

    public function clear(?string $id = null): void
    {
        $this->session->remove($id ?? $this->getId());
    }
}
