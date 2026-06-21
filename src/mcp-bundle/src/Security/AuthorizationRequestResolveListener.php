<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Security;

use League\Bundle\OAuth2ServerBundle\Event\AuthorizationRequestResolveEvent;
use Symfony\AI\McpBundle\OAuth\ConsentInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Resolves league's authorization request: binds the authenticated firewall
 * user as the OAuth subject and approves the request (auto-approve), or
 * delegates the decision to an optional consent strategy. Unauthenticated
 * visitors are redirected to the configured login path.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class AuthorizationRequestResolveListener
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly string $loginPath,
        private readonly ?ConsentInterface $consent = null,
    ) {
    }

    public function onResolve(AuthorizationRequestResolveEvent $event): void
    {
        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof UserInterface) {
            $event->setResponse(new RedirectResponse($this->loginPath));

            return;
        }

        $event->setUser($user);

        if (null !== $this->consent) {
            $this->consent->decide($event);

            return;
        }

        $event->resolveAuthorization(AuthorizationRequestResolveEvent::AUTHORIZATION_APPROVED);
    }
}
