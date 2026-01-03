<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\EventListener;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\EventListener\OAuthUnauthorizedListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class OAuthUnauthorizedListenerTest extends TestCase
{
    #[DataProvider('provideAuthenticationResponses')]
    public function testAddsWwwAuthenticateHeader(int $statusCode, string $expectedHeader)
    {
        $listener = new OAuthUnauthorizedListener('/_mcp');

        $request = Request::create('/_mcp');
        $response = new Response('', $statusCode);

        $event = $this->createResponseEvent($request, $response);
        $listener->onKernelResponse($event);

        $this->assertTrue($response->headers->has('WWW-Authenticate'));
        $this->assertSame($expectedHeader, $response->headers->get('WWW-Authenticate'));
    }

    public static function provideAuthenticationResponses(): iterable
    {
        yield '401 Unauthorized' => [
            Response::HTTP_UNAUTHORIZED,
            'Bearer resource_metadata="http://localhost/.well-known/oauth-protected-resource"',
        ];

        yield '403 Forbidden' => [
            Response::HTTP_FORBIDDEN,
            'Bearer resource_metadata="http://localhost/.well-known/oauth-protected-resource", error="insufficient_scope"',
        ];
    }

    public function testDoesNotModifyNonMcpEndpoints()
    {
        $listener = new OAuthUnauthorizedListener('/_mcp');

        $request = Request::create('/other-endpoint');
        $response = new Response('', Response::HTTP_UNAUTHORIZED);

        $event = $this->createResponseEvent($request, $response);
        $listener->onKernelResponse($event);

        $this->assertFalse($response->headers->has('WWW-Authenticate'));
    }

    public function testDoesNotModifySuccessResponses()
    {
        $listener = new OAuthUnauthorizedListener('/_mcp');

        $request = Request::create('/_mcp');
        $response = new Response('', Response::HTTP_OK);

        $event = $this->createResponseEvent($request, $response);
        $listener->onKernelResponse($event);

        $this->assertFalse($response->headers->has('WWW-Authenticate'));
    }

    public function testUsesForwardedHeaders()
    {
        $listener = new OAuthUnauthorizedListener('/_mcp');

        $request = Request::create('/_mcp');
        $request->headers->set('X-Forwarded-Proto', 'https');
        $request->headers->set('X-Forwarded-Host', 'mcp.example.com');
        $response = new Response('', Response::HTTP_UNAUTHORIZED);

        $event = $this->createResponseEvent($request, $response);
        $listener->onKernelResponse($event);

        $this->assertSame(
            'Bearer resource_metadata="https://mcp.example.com/.well-known/oauth-protected-resource"',
            $response->headers->get('WWW-Authenticate')
        );
    }

    public function testHandlesCustomMcpPath()
    {
        $listener = new OAuthUnauthorizedListener('/custom-mcp');

        $request = Request::create('/custom-mcp/endpoint');
        $response = new Response('', Response::HTTP_FORBIDDEN);

        $event = $this->createResponseEvent($request, $response);
        $listener->onKernelResponse($event);

        $this->assertTrue($response->headers->has('WWW-Authenticate'));
        $this->assertStringContainsString('error="insufficient_scope"', $response->headers->get('WWW-Authenticate'));
    }

    private function createResponseEvent(Request $request, Response $response): ResponseEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);

        return new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
    }
}