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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\Controller\McpController;
use Symfony\AI\McpSdk\Message\Factory;
use Symfony\AI\McpSdk\Server;
use Symfony\AI\McpSdk\Server\JsonRpcHandler;
use Symfony\AI\McpSdk\Server\Transport\Sse\Store\CachePoolStore;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

final class McpControllerTest extends TestCase
{
    private Server $server;
    private CachePoolStore&MockObject $store;
    private UrlGeneratorInterface&MockObject $urlGenerator;
    private McpController $controller;

    protected function setUp(): void
    {
        $messageFactory = new Factory();
        $jsonRpcHandler = new JsonRpcHandler($messageFactory, [], []);
        $this->server = new Server($jsonRpcHandler);

        $this->store = $this->createMock(CachePoolStore::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $this->controller = new McpController(
            $this->server,
            $this->store,
            $this->urlGenerator
        );
    }

    public function testSseCreatesStreamedResponse()
    {
        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with(
                '_mcp_messages',
                $this->callback(function ($params) {
                    return isset($params['id']) && $params['id'] instanceof Uuid;
                }),
                UrlGeneratorInterface::ABSOLUTE_URL
            )
            ->willReturn('https://example.com/mcp/messages/123e4567-e89b-12d3-a456-426614174000');

        $response = $this->controller->sse();

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame('text/event-stream', $response->headers->get('Content-Type'));
        $this->assertSame('no-cache', $response->headers->get('Cache-Control'));
        $this->assertSame('no', $response->headers->get('X-Accel-Buffering'));

        ob_start();
        $response->sendContent();
        ob_end_clean();
    }

    public function testMessagesStoresContent()
    {
        $content = '{"jsonrpc":"2.0","method":"initialize","params":{},"id":1}';
        $request = new Request(content: $content);

        $this->store->expects($this->once())
            ->method('push')
            ->with($this->isInstanceOf(Uuid::class), $content);

        $response = $this->controller->messages($request, Uuid::v4());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
    }
}
