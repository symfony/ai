<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Test;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

class TestSecurityMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $rolesHeader = $request->getHeaderLine('X-Test-Roles');
        if ('' === $rolesHeader) {
            return $handler->handle($request);
        }

        $roles = array_filter(explode(',', $rolesHeader));
        $user = new InMemoryUser('test@example.com', null, $roles);
        $token = new PostAuthenticationToken($user, 'mcp', $user->getRoles());
        $this->tokenStorage->setToken($token);

        return $handler->handle($request);
    }
}
