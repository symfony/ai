<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\Controller;

use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Server\Server;
use Mcp\Server\Session\InMemorySessionStore;
use Mcp\Server\Transport\StreamableHttpTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\Controller\McpController;
use Symfony\AI\McpBundle\Http\MiddlewareFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class McpControllerTest extends TestCase
{
    private TokenStorage $tokenStorage;

    protected function setUp(): void
    {
        $this->tokenStorage = new TokenStorage();
    }

    public function testSessionServesTheUserThatOpenedIt()
    {
        $controller = $this->controller(withSessions: true);

        $this->logIn('alice');
        $sessionId = $this->initialize($controller);

        $this->assertSame(Response::HTTP_OK, $this->listTools($controller, $sessionId)->getStatusCode());
    }

    public function testSessionRefusesAnotherUser()
    {
        $controller = $this->controller(withSessions: true);

        $this->logIn('alice');
        $sessionId = $this->initialize($controller);

        $this->logIn('bob');

        $this->assertSame(Response::HTTP_NOT_FOUND, $this->listTools($controller, $sessionId)->getStatusCode());
    }

    public function testAnotherUserCannotEndTheSession()
    {
        $controller = $this->controller(withSessions: true);

        $this->logIn('alice');
        $sessionId = $this->initialize($controller);

        $this->logIn('bob');
        $request = $this->request('DELETE', '', $sessionId);
        $this->assertSame(Response::HTTP_NOT_FOUND, $controller->handle($request)->getStatusCode());

        $this->logIn('alice');
        $this->assertSame(Response::HTTP_OK, $this->listTools($controller, $sessionId)->getStatusCode());
    }

    public function testSessionOpenedAnonymouslyRefusesAUser()
    {
        $controller = $this->controller(withSessions: true);

        $sessionId = $this->initialize($controller);

        $this->assertSame(Response::HTTP_OK, $this->listTools($controller, $sessionId)->getStatusCode());

        $this->logIn('alice');

        $this->assertSame(Response::HTTP_NOT_FOUND, $this->listTools($controller, $sessionId)->getStatusCode());
    }

    public function testTokenWithoutUserIdentifiesNoPrincipal()
    {
        $controller = $this->controller(withSessions: true);

        $this->tokenStorage->setToken(new NullToken());
        $sessionId = $this->initialize($controller);

        $this->assertSame(Response::HTTP_OK, $this->listTools($controller, $sessionId)->getStatusCode());
    }

    public function testPrincipalSetByTheApplicationWins()
    {
        $controller = $this->controller(withSessions: true);

        $this->logIn('alice');
        $initialize = $this->initializeRequest();
        $initialize->attributes->set(StreamableHttpTransport::PRINCIPAL_ATTRIBUTE, 'tenant-1');
        $sessionId = $controller->handle($initialize)->headers->get(StreamableHttpTransport::SESSION_HEADER);
        $this->assertIsString($sessionId);

        $this->assertSame(Response::HTTP_NOT_FOUND, $this->listTools($controller, $sessionId)->getStatusCode());

        $request = $this->listToolsRequest($sessionId);
        $request->attributes->set(StreamableHttpTransport::PRINCIPAL_ATTRIBUTE, 'tenant-1');
        $this->assertSame(Response::HTTP_OK, $controller->handle($request)->getStatusCode());
    }

    public function testControllerWorksWithoutSecurity()
    {
        $controller = $this->controller(withSessions: true, tokenStorage: null);

        $sessionId = $this->initialize($controller);

        $this->assertSame(Response::HTTP_OK, $this->listTools($controller, $sessionId)->getStatusCode());
    }

    public function testServerWithoutSessionsAnswersWithoutSessionId()
    {
        $controller = $this->controller(withSessions: false);

        $response = $controller->handle($this->initializeRequest());

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertFalse($response->headers->has(StreamableHttpTransport::SESSION_HEADER));
        $this->assertSame(Response::HTTP_OK, $controller->handle($this->listToolsRequest(null))->getStatusCode());
    }

    public function testRejectsABodyOverTheConfiguredLimit()
    {
        $response = $this->controller(withSessions: false, maxBodyBytes: 10)->handle($this->request('POST', str_repeat('a', 100)));

        $this->assertSame(Response::HTTP_REQUEST_ENTITY_TOO_LARGE, $response->getStatusCode());
    }

    public function testAcceptsABodyWithinTheConfiguredLimit()
    {
        $response = $this->controller(withSessions: false, maxBodyBytes: 1024)->handle($this->request('POST', str_repeat('a', 100)));

        $this->assertNotSame(Response::HTTP_REQUEST_ENTITY_TOO_LARGE, $response->getStatusCode());
    }

    public function testKeepsTheSdkLimitWhenNoneIsConfigured()
    {
        $response = $this->controller(withSessions: false)->handle($this->request('POST', str_repeat('a', 100)));

        $this->assertNotSame(Response::HTTP_REQUEST_ENTITY_TOO_LARGE, $response->getStatusCode());
    }

    private function controller(bool $withSessions, TokenStorage|false|null $tokenStorage = false, ?int $maxBodyBytes = null): McpController
    {
        $builder = Server::builder()->setServerInfo('test', '1.0.0');
        if ($withSessions) {
            $builder->setSession(new InMemorySessionStore());
        }

        $psr17Factory = new Psr17Factory();

        return new McpController(
            $builder->build(),
            new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory),
            new HttpFoundationFactory(),
            $psr17Factory,
            $psr17Factory,
            new MiddlewareFactory(),
            maxBodyBytes: $maxBodyBytes,
            tokenStorage: false === $tokenStorage ? $this->tokenStorage : $tokenStorage,
        );
    }

    private function logIn(string $identifier): void
    {
        $this->tokenStorage->setToken(new UsernamePasswordToken(new InMemoryUser($identifier, null), 'main'));
    }

    private function initialize(McpController $controller): string
    {
        $sessionId = $controller->handle($this->initializeRequest())->headers->get(StreamableHttpTransport::SESSION_HEADER);
        $this->assertIsString($sessionId);

        return $sessionId;
    }

    private function listTools(McpController $controller, string $sessionId): Response
    {
        return $controller->handle($this->listToolsRequest($sessionId));
    }

    private function initializeRequest(): Request
    {
        return $this->request('POST', json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => ProtocolVersion::V2025_06_18->value,
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 'test', 'version' => '1.0.0'],
            ],
        ], \JSON_THROW_ON_ERROR));
    }

    private function listToolsRequest(?string $sessionId): Request
    {
        return $this->request('POST', json_encode(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'], \JSON_THROW_ON_ERROR), $sessionId);
    }

    private function request(string $method, string $body, ?string $sessionId = null): Request
    {
        $server = [
            'HTTP_HOST' => 'localhost',
            'HTTP_ACCEPT' => 'application/json, text/event-stream',
            'CONTENT_TYPE' => 'application/json',
        ];

        if (null !== $sessionId) {
            $server['HTTP_MCP_SESSION_ID'] = $sessionId;
        }

        return Request::create('http://localhost/mcp', $method, server: $server, content: $body);
    }
}
