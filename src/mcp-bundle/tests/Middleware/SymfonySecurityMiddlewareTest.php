<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\Middleware;

use Http\Discovery\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\AI\McpBundle\Middleware\SymfonySecurityMiddleware;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

final class SymfonySecurityMiddlewareTest extends TestCase
{
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    public function testPassesThroughWithoutOAuthClaims(): void
    {
        $request = $this->createRequest();
        $expectedResponse = $this->factory->createResponse(200);

        $tokenStorage = self::createMock(TokenStorageInterface::class);
        $tokenStorage->expects(self::never())->method('setToken');

        $handler = self::createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->willReturn($expectedResponse);

        $middleware = new SymfonySecurityMiddleware($tokenStorage, responseFactory: $this->factory);
        $response = $middleware->process($request, $handler);

        self::assertSame($expectedResponse, $response);
    }

    public function testReturns401WhenSubMissing(): void
    {
        $request = $this->createRequest(['email' => 'user@test.com']);

        $middleware = new SymfonySecurityMiddleware(
            self::createStub(TokenStorageInterface::class),
            responseFactory: $this->factory,
        );
        $response = $middleware->process($request, self::createStub(RequestHandlerInterface::class));

        self::assertSame(401, $response->getStatusCode());
    }

    public function testReturns401WhenEmailMissing(): void
    {
        $request = $this->createRequest(['sub' => 'user-id']);

        $middleware = new SymfonySecurityMiddleware(
            self::createStub(TokenStorageInterface::class),
            responseFactory: $this->factory,
        );
        $response = $middleware->process($request, self::createStub(RequestHandlerInterface::class));

        self::assertSame(401, $response->getStatusCode());
    }

    public function testReturns401WhenSubIsEmpty(): void
    {
        $request = $this->createRequest(['sub' => '', 'email' => 'user@test.com']);

        $middleware = new SymfonySecurityMiddleware(
            self::createStub(TokenStorageInterface::class),
            responseFactory: $this->factory,
        );
        $response = $middleware->process($request, self::createStub(RequestHandlerInterface::class));

        self::assertSame(401, $response->getStatusCode());
    }

    public function testSetsSecurityTokenWithValidClaims(): void
    {
        $request = $this->createRequest([
            'sub' => 'user-id',
            'email' => 'user@test.com',
            'roles' => ['ROLE_ADMIN'],
        ]);
        $expectedResponse = $this->factory->createResponse(200);

        $tokenStorage = self::createMock(TokenStorageInterface::class);
        $tokenStorage->expects(self::once())
            ->method('setToken')
            ->with(self::callback(static function (PostAuthenticationToken $token): bool {
                return 'user@test.com' === $token->getUserIdentifier()
                    && \in_array('ROLE_MCP_USER', $token->getRoleNames(), true)
                    && \in_array('ROLE_ADMIN', $token->getRoleNames(), true);
            }));

        $handler = self::createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($expectedResponse);

        $middleware = new SymfonySecurityMiddleware($tokenStorage, responseFactory: $this->factory);
        $response = $middleware->process($request, $handler);

        self::assertSame($expectedResponse, $response);
    }

    public function testUsesCustomRolesClaim(): void
    {
        $request = $this->createRequest([
            'sub' => 'user-id',
            'email' => 'user@test.com',
            'groups' => ['ROLE_EDITOR'],
        ]);

        $tokenStorage = self::createMock(TokenStorageInterface::class);
        $tokenStorage->expects(self::once())
            ->method('setToken')
            ->with(self::callback(static function (PostAuthenticationToken $token): bool {
                return \in_array('ROLE_EDITOR', $token->getRoleNames(), true);
            }));

        $handler = self::createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($this->factory->createResponse(200));

        $middleware = new SymfonySecurityMiddleware($tokenStorage, rolesClaim: 'groups', responseFactory: $this->factory);
        $middleware->process($request, $handler);
    }

    public function testFiltersNonStringRoles(): void
    {
        $request = $this->createRequest([
            'sub' => 'user-id',
            'email' => 'user@test.com',
            'roles' => ['ROLE_VALID', 123, null, 'ROLE_ALSO_VALID'],
        ]);

        $tokenStorage = self::createMock(TokenStorageInterface::class);
        $tokenStorage->expects(self::once())
            ->method('setToken')
            ->with(self::callback(static function (PostAuthenticationToken $token): bool {
                $roles = $token->getRoleNames();

                return \in_array('ROLE_VALID', $roles, true)
                    && \in_array('ROLE_ALSO_VALID', $roles, true)
                    && 3 === \count($roles);
            }));

        $handler = self::createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($this->factory->createResponse(200));

        $middleware = new SymfonySecurityMiddleware($tokenStorage, responseFactory: $this->factory);
        $middleware->process($request, $handler);
    }

    private function createRequest(?array $claims = null): ServerRequestInterface
    {
        $request = $this->factory->createServerRequest('POST', '/mcp');
        if (null !== $claims) {
            $request = $request->withAttribute('oauth.claims', $claims);
        }

        return $request;
    }
}
