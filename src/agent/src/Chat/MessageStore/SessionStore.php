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
use Symfony\AI\Platform\Message\MessageBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;

final readonly class SessionStore implements MessageStoreInterface
{
    private SessionInterface $session;

    public function __construct(
        RequestStack $requestStack,
    ) {
        if (!class_exists(RequestStack::class)) {
            throw new RuntimeException('For using the SessionStore as message store, the symfony/http-foundation package is required. Try running "composer require symfony/http-foundation".');
        }

        $this->session = $requestStack->getSession();
    }

    public function save(MessageBagInterface $messages): void
    {
        $this->session->set($messages->getId()->toRfc4122(), $messages);
    }

    public function load(AbstractUid&TimeBasedUidInterface $id): MessageBagInterface
    {
        return $this->session->get($id->toRfc4122(), new MessageBag());
    }

    public function clear(): void
    {
        $this->session->clear();
    }
}
