<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat\Bridge\HttpFoundation;

use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Chat\MessageStoreInterface;
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
        private string $sessionKey = 'messages',
    ) {
        if (!class_exists(RequestStack::class)) {
            throw new RuntimeException('For using the SessionStore as message store, the symfony/http-foundation package is required. Try running "composer require symfony/http-foundation".');
        }
        $this->session = $requestStack->getSession();
    }

    public function save(MessageBag $messages): void
    {
        $this->session->set($this->sessionKey, $messages);
    }

    public function load(): MessageBag
    {
        return $this->session->get($this->sessionKey, new MessageBag());
    }

    public function clear(): void
    {
        $this->session->remove($this->sessionKey);
    }
}
