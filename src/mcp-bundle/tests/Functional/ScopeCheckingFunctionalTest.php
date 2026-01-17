<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\Functional;

use Symfony\AI\McpBundle\Tests\Functional\App\TestKernel;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for OAuth scope checking on MCP tools.
 */
final class ScopeCheckingFunctionalTest extends WebTestCase
{
    protected function tearDown(): void
    {
        // MCP SDK sets exception handlers that need to be cleaned up
        restore_exception_handler();
        parent::tearDown();
    }

    public function testPublicToolAccessibleWithAnyScope()
    {
        $client = static::createClient();
        $sessionId = $this->initializeSession($client, 'read');

        // First, list available tools
        $client->request(
            'POST',
            '/_mcp',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer read',
                'HTTP_MCP_SESSION_ID' => $sessionId,
            ],
            json_encode([
                'jsonrpc' => '2.0',
                'id' => 2,
                'method' => 'tools/list',
                'params' => new \stdClass(),
            ])
        );
        $listResponse = json_decode($client->getResponse()->getContent(), true);

        $this->callTool($client, $sessionId, 'public-tool', 'read');

        $response = $client->getResponse();
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        // Debug: show tools list and call response
        $this->assertArrayHasKey('result', $data, 'Tools list: '.json_encode($listResponse)."\nCall response: ".$response->getContent());
    }

    public function testAdminToolRequiresAdminScope()
    {
        $client = static::createClient();
        $sessionId = $this->initializeSession($client, 'read_write');

        $this->callTool($client, $sessionId, 'admin-tool', 'read_write');

        $response = $client->getResponse();

        // RFC 6750 Section 3.1: Insufficient scope returns HTTP 403
        $this->assertSame(403, $response->getStatusCode(), 'Response: '.$response->getContent());

        // Verify WWW-Authenticate header with error and scope
        $wwwAuth = $response->headers->get('WWW-Authenticate');
        $this->assertNotNull($wwwAuth, 'Expected WWW-Authenticate header');
        $this->assertStringContainsString('error="insufficient_scope"', $wwwAuth);
        $this->assertStringContainsString('scope="admin"', $wwwAuth);
    }

    public function testAdminToolAccessibleWithAdminScope()
    {
        $client = static::createClient();
        $sessionId = $this->initializeSession($client, 'admin');

        $this->callTool($client, $sessionId, 'admin-tool', 'admin');

        $response = $client->getResponse();
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('result', $data);
        $this->assertFalse($data['result']['isError'] ?? true, 'Expected isError to be false');
    }

    public function testUnauthenticatedRequestReturns401()
    {
        $client = static::createClient();

        // Try to initialize without auth
        $client->request(
            'POST',
            '/_mcp',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '2025-06-18',
                    'capabilities' => new \stdClass(),
                    'clientInfo' => ['name' => 'test', 'version' => '1.0'],
                ],
            ])
        );

        $response = $client->getResponse();
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testAdminPromptRequiresAdminScope()
    {
        $client = static::createClient();
        $sessionId = $this->initializeSession($client, 'read');

        $this->callPrompt($client, $sessionId, 'admin-prompt', 'read');

        $response = $client->getResponse();

        // RFC 6750 Section 3.1: Insufficient scope returns HTTP 403
        $this->assertSame(403, $response->getStatusCode(), 'Response: '.$response->getContent());

        // Verify WWW-Authenticate header with error and scope
        $wwwAuth = $response->headers->get('WWW-Authenticate');
        $this->assertNotNull($wwwAuth, 'Expected WWW-Authenticate header');
        $this->assertStringContainsString('error="insufficient_scope"', $wwwAuth);
        $this->assertStringContainsString('scope="admin"', $wwwAuth);
    }

    public function testAdminPromptAccessibleWithAdminScope()
    {
        $client = static::createClient();
        $sessionId = $this->initializeSession($client, 'admin');

        $this->callPrompt($client, $sessionId, 'admin-prompt', 'admin');

        $response = $client->getResponse();
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('result', $data, 'Expected result. Got: '.$response->getContent());
        $this->assertArrayNotHasKey('error', $data, 'Unexpected error. Got: '.$response->getContent());
        // Prompt results have 'messages' array, not 'isError'
        $this->assertArrayHasKey('messages', $data['result'], 'Expected messages in result. Got: '.$response->getContent());
    }

    public function testAdminResourceRequiresAdminScope()
    {
        $client = static::createClient();
        $sessionId = $this->initializeSession($client, 'read');

        $this->readResource($client, $sessionId, 'admin://secret', 'read');

        $response = $client->getResponse();

        // RFC 6750 Section 3.1: Insufficient scope returns HTTP 403
        $this->assertSame(403, $response->getStatusCode(), 'Response: '.$response->getContent());

        // Verify WWW-Authenticate header with error and scope
        $wwwAuth = $response->headers->get('WWW-Authenticate');
        $this->assertNotNull($wwwAuth, 'Expected WWW-Authenticate header');
        $this->assertStringContainsString('error="insufficient_scope"', $wwwAuth);
        $this->assertStringContainsString('scope="admin"', $wwwAuth);
    }

    public function testAdminResourceAccessibleWithAdminScope()
    {
        $client = static::createClient();
        $sessionId = $this->initializeSession($client, 'admin');

        $this->readResource($client, $sessionId, 'admin://secret', 'admin');

        $response = $client->getResponse();
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('result', $data);
    }

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    private function initializeSession(KernelBrowser $client, string $scopes): string
    {
        $client->request(
            'POST',
            '/_mcp',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$scopes,
            ],
            json_encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '2025-06-18',
                    'capabilities' => new \stdClass(),
                    'clientInfo' => ['name' => 'test', 'version' => '1.0'],
                ],
            ])
        );

        $response = $client->getResponse();
        $this->assertSame(200, $response->getStatusCode(), 'Initialize failed: '.$response->getContent());

        // Get session ID from response header
        $sessionId = $response->headers->get('Mcp-Session-Id');
        $this->assertNotNull($sessionId, 'No session ID returned');

        return $sessionId;
    }

    private function callTool(KernelBrowser $client, string $sessionId, string $toolName, string $scopes): void
    {
        $client->request(
            'POST',
            '/_mcp',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$scopes,
                'HTTP_MCP_SESSION_ID' => $sessionId,
            ],
            json_encode([
                'jsonrpc' => '2.0',
                'id' => 2,
                'method' => 'tools/call',
                'params' => [
                    'name' => $toolName,
                    'arguments' => new \stdClass(),
                ],
            ])
        );
    }

    private function callPrompt(KernelBrowser $client, string $sessionId, string $promptName, string $scopes): void
    {
        $client->request(
            'POST',
            '/_mcp',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$scopes,
                'HTTP_MCP_SESSION_ID' => $sessionId,
            ],
            json_encode([
                'jsonrpc' => '2.0',
                'id' => 2,
                'method' => 'prompts/get',
                'params' => [
                    'name' => $promptName,
                    'arguments' => new \stdClass(),
                ],
            ])
        );
    }

    private function readResource(KernelBrowser $client, string $sessionId, string $uri, string $scopes): void
    {
        $client->request(
            'POST',
            '/_mcp',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$scopes,
                'HTTP_MCP_SESSION_ID' => $sessionId,
            ],
            json_encode([
                'jsonrpc' => '2.0',
                'id' => 2,
                'method' => 'resources/read',
                'params' => [
                    'uri' => $uri,
                ],
            ])
        );
    }
}
