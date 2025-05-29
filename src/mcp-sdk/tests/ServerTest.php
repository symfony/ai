<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpLlm\McpSdk\Tests;

use PhpLlm\McpSdk\Server;
use PhpLlm\McpSdk\Server\JsonRpcHandler;
use PhpLlm\McpSdk\Tests\Fixtures\InMemoryTransport;
use PHPUnit\Framework\MockObject\Stub\Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ServerTest extends TestCase
{
    public function testJsonExceptions(): void
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
        $handler->expects($this->exactly(2))->method('process')->willReturnOnConsecutiveCalls(new Exception(new \JsonException('foobar')), 'success');

        $transport = $this->getMockBuilder(InMemoryTransport::class)
            ->setConstructorArgs([['foo', 'bar']])
            ->onlyMethods(['send'])
            ->getMock();
        $transport->expects($this->once())->method('send')->with('success');

        $server = new Server($handler, $logger);
        $server->connect($transport);
    }
}
