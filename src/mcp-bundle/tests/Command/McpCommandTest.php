<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\Command;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\Command\McpCommand;
use Symfony\AI\McpSdk\Server;
use Symfony\AI\McpSdk\Server\Transport\Stdio\SymfonyConsoleTransport;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class McpCommandTest extends TestCase
{
    public function testExecuteStartsMcpServer()
    {
        /** @var Server&MockObject $server */
        $server = $this->createMock(Server::class);
        $server->expects($this->once())
            ->method('connect')
            ->with($this->isInstanceOf(SymfonyConsoleTransport::class));

        $command = new McpCommand($server);

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $command->run($input, $output);

        $this->assertSame(0, $exitCode);
    }

    public function testCommandIsNamedCorrectly()
    {
        /** @var Server&MockObject $server */
        $server = $this->createMock(Server::class);
        $command = new McpCommand($server);

        $this->assertSame('mcp:server', $command->getName());
        $this->assertSame('Starts an MCP server', $command->getDescription());
    }
}
