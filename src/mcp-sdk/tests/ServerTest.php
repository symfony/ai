<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpSdk\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\MockObject\Stub\Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\McpSdk\Exception\TransportNotConnectedException;
use Symfony\AI\McpSdk\Message\Error;
use Symfony\AI\McpSdk\Message\Factory;
use Symfony\AI\McpSdk\Message\Request;
use Symfony\AI\McpSdk\Message\Response;
use Symfony\AI\McpSdk\Server;
use Symfony\AI\McpSdk\Server\JsonRpcHandler;
use Symfony\AI\McpSdk\Server\KeepAliveSession\KeepAliveSession;
use Symfony\AI\McpSdk\Tests\Fixtures\InMemoryTransport;
use Symfony\Component\Clock\MockClock;

#[Small]
#[CoversClass(Server::class)]
class ServerTest extends TestCase
{
    public function testJsonExceptions()
    {
        $logger = $this->getMockBuilder(NullLogger::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['error'])
            ->getMock();
        $logger->expects($this->once())->method('error');

        $handler = $this->getMockBuilder(JsonRpcHandler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['process'])
            ->getMock();
        $handler->expects($this->exactly(2))->method('process')->willReturnOnConsecutiveCalls(new Exception(new \JsonException('foobar')), ['success']);

        $transport = $this->getMockBuilder(InMemoryTransport::class)
            ->setConstructorArgs([['foo', 'bar']])
            ->onlyMethods(['send'])
            ->getMock();
        $transport->expects($this->once())->method('send')->with('success');

        $server = new Server($handler, new MockClock(), logger: $logger);
        $server->connect($transport);
    }

    public function testSendRequest()
    {
        $transport = $this->getMockBuilder(InMemoryTransport::class)
            ->setConstructorArgs([])
            ->onlyMethods(['send'])
            ->getMock();
        $transport->expects($this->once())->method('send')->with('{"jsonrpc":"2.0","id":"1","method":"ping","params":{}}');

        $logger = new NullLogger();
        $handler = new JsonRpcHandler(new Factory(), [], [], $logger);
        $server = new Server($handler, new MockClock(), logger: $logger);
        $server->connect($transport);

        $server->sendRequest(new Request('1', 'ping', []));
    }

    public function testThrowExceptionWhenTransportIsNotConnected()
    {
        $logger = new NullLogger();
        $handler = new JsonRpcHandler(new Factory(), [], [], $logger);
        $server = new Server($handler, new MockClock(), logger: $logger);

        $this->expectException(TransportNotConnectedException::class);
        $server->sendRequest(new Request('1', 'ping', []));
    }

    public function testResponseCallbackIsCalled()
    {
        $callbackCalled = false;
        $receivedResponse = null;

        $logger = new NullLogger();
        $handler = new JsonRpcHandler(new Factory(), [], [], $logger);
        $server = new Server($handler, new MockClock(), logger: $logger);

        $callback = function (Response|Error $response) use (&$callbackCalled, &$receivedResponse) {
            $callbackCalled = true;
            $receivedResponse = $response;
        };

        $server->connect(new InMemoryTransport());
        $server->sendRequest(new Request('1', 'ping', []), $callback);

        $server->connect(new InMemoryTransport([
            '{"jsonrpc":"2.0","id":"1","result":{}}',
        ]));

        $this->assertTrue($callbackCalled);
        $this->assertInstanceOf(Response::class, $receivedResponse);
    }

    public function testWarningIsLoggedWhenResponseHandlerIsNotFound()
    {
        $logger = $this->getMockBuilder(NullLogger::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['warning'])
            ->getMock();
        $logger->expects($this->once())->method('warning')->with('No handler found for response id "1".');

        $handler = new JsonRpcHandler(new Factory(), [], [], $logger);
        $server = new Server($handler, new MockClock(), logger: $logger);

        $server->connect(new InMemoryTransport([
            '{"jsonrpc":"2.0","id":"1","result":{}}',
        ]));
    }

    public function testPendingResponseIsResolvedWhenResponseIsReceived()
    {
        $logger = $this->getMockBuilder(NullLogger::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['warning'])
            ->getMock();
        $logger->expects($this->once())->method('warning')->with('No handler found for response id "1".');

        $handler = new JsonRpcHandler(new Factory(), [], [], $logger);
        $server = new Server($handler, new MockClock(), logger: $logger);

        $callbackCalled = false;
        $receivedResponse = null;
        $callback = function (Response|Error $response) use (&$callbackCalled, &$receivedResponse) {
            $callbackCalled = true;
            $receivedResponse = $response;
        };

        $server->connect(new InMemoryTransport());
        $server->sendRequest(new Request('1', 'ping', []), $callback);

        $server->connect(new InMemoryTransport([
            '{"jsonrpc":"2.0","id":"1","result":{}}',
            '{"jsonrpc":"2.0","id":"1","result":{}}',
        ]));

        $this->assertTrue($callbackCalled);
        $this->assertInstanceOf(Response::class, $receivedResponse);
    }

    public function testPendingResponseTimesOut()
    {
        $logger = $this->getMockBuilder(NullLogger::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['warning'])
            ->getMock();
        $logger->expects($this->once())->method('warning')->with('Pending response timed out');

        $clock = new MockClock();
        $handler = new JsonRpcHandler(new Factory(), [], [], $logger);
        $server = new Server($handler, $clock, logger: $logger);

        $callbackCalled = false;
        $receivedResponse = null;
        $callback = function (Response|Error $response) use (&$callbackCalled, &$receivedResponse) {
            $callbackCalled = true;
            $receivedResponse = $response;
        };

        $server->connect(new InMemoryTransport([]));
        $server->sendRequest(new Request('1', 'ping', []), $callback);

        $clock->sleep(30.001);

        $server->connect(new InMemoryTransport([]));

        $this->assertTrue($callbackCalled);
        $this->assertInstanceOf(Error::class, $receivedResponse);
    }

    public function testKeepAliveSessionSendsPing()
    {
        $clock = new MockClock();
        $logger = new NullLogger();
        $handler = new JsonRpcHandler(new Factory(), [], [], $logger);
        $keepAlive = new KeepAliveSession($clock, new \DateInterval('PT0S'));

        $transport = $this->getMockBuilder(InMemoryTransport::class)
            ->setConstructorArgs([[]])
            ->onlyMethods(['send'])
            ->getMock();

        $transport->expects($this->once())->method('send')->with($this->callback(function (string $payload): bool {
            $data = json_decode($payload, true);
            if (!\is_array($data)) {
                return false;
            }

            return ($data['method'] ?? null) === 'ping';
        }));

        $server = new Server($handler, $clock, keepAliveSession: $keepAlive, logger: $logger);

        $server->connect($transport);
    }

    public function testKeepAlivePingTimesOut()
    {
        $clock = new MockClock();

        $logger = $this->getMockBuilder(NullLogger::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['warning'])
            ->getMock();
        $matcher = $this->exactly(2);
        $logger->expects($matcher)
            ->method('warning')
            ->willReturnCallback(function (string $message) use ($matcher) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertEquals('KeepAlive ping returned error response', $message),
                    2 => $this->assertEquals('Pending response timed out', $message),
                    default => $this->fail('Unexpected number of invocations'),
                };
            });

        $handler = new JsonRpcHandler(new Factory(), [], [], $logger);
        $keepAlive = new KeepAliveSession($clock, new \DateInterval('PT0S'));

        // First connect: triggers a ping send immediately
        $server = new Server($handler, $clock, $keepAlive, $logger);
        $server->connect(new InMemoryTransport([]));

        // Let the pending ping timeout
        $clock->sleep(30.001);

        // Second connect: triggers GC which should warn about timeout
        $server->connect(new InMemoryTransport([]));
    }
}
