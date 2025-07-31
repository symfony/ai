<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\Server\RequestHandler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\Exception\RuntimeException;
use Symfony\AI\McpSdk\Capability\Tool\ToolCall;
use Symfony\AI\McpSdk\Capability\Tool\ToolCallResult;
use Symfony\AI\McpSdk\Capability\Tool\ToolExecutorInterface;
use Symfony\AI\McpSdk\Exception\ToolExecutionException;
use Symfony\AI\McpSdk\Exception\ToolNotFoundException;
use Symfony\AI\McpSdk\Message\Error;
use Symfony\AI\McpSdk\Message\Request;
use Symfony\AI\McpSdk\Message\Response;
use Symfony\AI\McpSdk\Server\RequestHandler\ToolCallHandler;

#[CoversClass(ToolCallHandler::class)]
final class ToolCallHandlerTest extends TestCase
{
    private ToolExecutorInterface&MockObject $toolExecutor;
    private ToolCallHandler $handler;

    protected function setUp(): void
    {
        $this->toolExecutor = $this->createMock(ToolExecutorInterface::class);
        $this->handler = new ToolCallHandler($this->toolExecutor);
    }

    public function testSupportsToolCallMethod()
    {
        $request = new Request(id: 'test-id', method: 'tools/call', params: ['name' => 'test-tool']);
        $this->assertTrue($this->handler->supports($request));
    }

    public function testDoesNotSupportOtherMethods()
    {
        $request = new Request(id: 'test-id', method: 'some/other', params: []);
        $this->assertFalse($this->handler->supports($request));
    }

    public function testCreateResponseWithTextResult()
    {
        $request = new Request(
            id: 'test-id',
            method: 'tools/call',
            params: [
                'name' => 'test-tool',
                'arguments' => ['foo' => 'bar'],
            ]
        );

        $toolResult = new ToolCallResult('Hello from tool', 'text');

        $this->toolExecutor->expects($this->once())
            ->method('call')
            ->with($this->callback(function (ToolCall $call) {
                return 'test-tool' === $call->name
                    && $call->arguments === ['foo' => 'bar'];
            }))
            ->willReturn($toolResult);

        $response = $this->handler->createResponse($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('test-id', $response->id);
        $this->assertSame([
            'content' => [[
                'type' => 'text',
                'text' => 'Hello from tool',
            ]],
            'isError' => false,
        ], $response->result);
    }

    public function testCreateResponseWithImageResult()
    {
        $request = new Request(id: 'test-id', method: 'tools/call', params: ['name' => 'image-tool']);

        $toolResult = new ToolCallResult('base64imagedata', 'image', 'image/png');

        $this->toolExecutor->expects($this->once())
            ->method('call')
            ->willReturn($toolResult);

        $response = $this->handler->createResponse($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame([
            'content' => [[
                'type' => 'image',
                'data' => 'base64imagedata',
                'mimeType' => 'image/png',
            ]],
            'isError' => false,
        ], $response->result);
    }

    public function testCreateResponseWithResourceResult()
    {
        $request = new Request(id: 'test-id', method: 'tools/call', params: ['name' => 'resource-tool']);

        $toolResult = new ToolCallResult(
            'Resource content',
            'resource',
            'text/html',
            false,
            'https://example.com/resource'
        );

        $this->toolExecutor->expects($this->once())
            ->method('call')
            ->willReturn($toolResult);

        $response = $this->handler->createResponse($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame([
            'content' => [[
                'type' => 'resource',
                'resource' => [
                    'uri' => 'https://example.com/resource',
                    'mimeType' => 'text/html',
                    'text' => 'Resource content',
                ],
            ]],
            'isError' => false,
        ], $response->result);
    }

    public function testCreateResponseWithError()
    {
        $request = new Request(id: 'test-id', method: 'tools/call', params: ['name' => 'error-tool']);

        $toolResult = new ToolCallResult('Error occurred', 'text', 'text/plain', true);

        $this->toolExecutor->expects($this->once())
            ->method('call')
            ->willReturn($toolResult);

        $response = $this->handler->createResponse($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertTrue($response->result['isError']);
    }

    public function testCreateResponseHandlesExecutionException()
    {
        $request = new Request(id: 'test-id', method: 'tools/call', params: ['name' => 'failing-tool']);

        $this->toolExecutor->expects($this->once())
            ->method('call')
            ->willThrowException(new ToolExecutionException(new ToolCall('test-id', 'failing-tool'), new RuntimeException('Tool failed')));

        $response = $this->handler->createResponse($request);

        $this->assertInstanceOf(Error::class, $response);
        $this->assertSame('test-id', $response->id);
        $this->assertSame(-32603, $response->code);
        $this->assertSame('Error while executing tool', $response->message);
    }

    public function testCreateResponseHandlesToolNotFoundException()
    {
        $request = new Request(id: 'test-id', method: 'tools/call', params: ['name' => 'unknown-tool']);

        $this->toolExecutor->expects($this->once())
            ->method('call')
            ->willThrowException(new ToolNotFoundException(new ToolCall('test-id', 'unknown-tool')));

        $response = $this->handler->createResponse($request);

        $this->assertInstanceOf(Error::class, $response);
        $this->assertSame('Error while executing tool', $response->message);
    }

    public function testCreateResponseWithoutArguments()
    {
        $request = new Request(id: 'test-id', method: 'tools/call', params: ['name' => 'no-args-tool']);

        $toolResult = new ToolCallResult('Success');

        $this->toolExecutor->expects($this->once())
            ->method('call')
            ->with($this->callback(function (ToolCall $call) {
                return 'no-args-tool' === $call->name
                    && [] === $call->arguments;
            }))
            ->willReturn($toolResult);

        $response = $this->handler->createResponse($request);

        $this->assertInstanceOf(Response::class, $response);
    }
}
