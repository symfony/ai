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

use Mcp\Server\Transport\Http\OAuth\ResourceOwner;
use Mcp\Server\Transport\Http\OAuth\ResourceOwnerResolverInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Default resource owner resolver: the user authenticated on the firewall in
 * front of the authorize endpoint becomes the OAuth subject (its user
 * identifier is used as the JWT "sub" claim).
 *
 * When no user is authenticated, the host firewall normally intercepts the
 * request with its own entry point before the controller runs; this resolver
 * falls back to a redirect to the configured login path.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SecurityResourceOwnerResolver implements ResourceOwnerResolverInterface
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly string $loginPath = '/login',
    ) {
    }

    public function resolve(ServerRequestInterface $request): ?ResourceOwner
    {
        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof UserInterface) {
            return null;
        }

        return new ResourceOwner($user->getUserIdentifier());
    }

    public function onUnauthenticated(ServerRequestInterface $request): ResponseInterface
    {
        return $this->responseFactory
            ->createResponse(302)
            ->withHeader('Location', $this->loginPath)
            ->withHeader('Cache-Control', 'no-store');
    }
}
