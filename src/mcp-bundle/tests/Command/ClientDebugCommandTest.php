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

use Mcp\Client;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\Client\McpClient;
use Symfony\AI\McpBundle\Client\McpClientInterface;
use Symfony\AI\McpBundle\Client\ServerConnection;
use Symfony\AI\McpBundle\Command\ClientDebugCommand;
use Symfony\AI\McpBundle\Tests\Double\InMemoryTransport;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ClientDebugCommandTest extends TestCase
{
    public function testOverviewListsClientsWithoutConnecting()
    {
        $transport = $this->populatedTransport();
        $tester = $this->createTester(['research' => ['github' => $transport, 'filesystem' => new InMemoryTransport()]]);

        $this->assertSame(Command::SUCCESS, $tester->execute([]));

        $display = $tester->getDisplay();
        $this->assertStringContainsString('research', $display);
        $this->assertStringContainsString('github, filesystem', $display);
        // A "which clients do I have" question must not spawn processes or open sessions.
        $this->assertSame(0, $transport->connectCount);
    }

    public function testWarnsWhenNoClientIsConfigured()
    {
        $tester = $this->createTester([]);

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('No MCP client is configured.', $tester->getDisplay());
    }

    public function testDescribesAServer()
    {
        $tester = $this->createTester(['research' => ['github' => $this->populatedTransport()]]);

        $this->assertSame(Command::SUCCESS, $tester->execute(['client' => 'research', 'server' => 'github']));

        $display = $tester->getDisplay();
        $this->assertStringContainsString('MCP client "research" → server "github"', $display);
        $this->assertStringContainsString('test-server', $display);
        $this->assertStringContainsString('Be nice.', $display);
        $this->assertStringContainsString('Tools (1)', $display);
        $this->assertStringContainsString('read_file', $display);
        $this->assertStringContainsString('Read a file from disk.', $display);
        $this->assertStringContainsString('path', $display);
    }

    public function testSingleServerClientNeedsNoServerArgument()
    {
        $tester = $this->createTester(['research' => ['github' => $this->populatedTransport()]]);

        $this->assertSame(Command::SUCCESS, $tester->execute(['client' => 'research']));
        $this->assertStringContainsString('server "github"', $tester->getDisplay());
    }

    public function testMultiServerClientRequiresTheServerArgument()
    {
        $tester = $this->createTester(['research' => ['github' => new InMemoryTransport(), 'filesystem' => new InMemoryTransport()]]);

        $this->assertSame(Command::INVALID, $tester->execute(['client' => 'research']));
        $this->assertStringContainsString('connects to several servers, name the one to', $tester->getDisplay());
    }

    public function testReportsUnknownClient()
    {
        $tester = $this->createTester(['research' => ['github' => new InMemoryTransport()]]);

        $this->assertSame(Command::INVALID, $tester->execute(['client' => 'missing']));
        $this->assertStringContainsString('No MCP client named "missing" is configured.', $tester->getDisplay());
        $this->assertStringContainsString('Available: research.', $tester->getDisplay());
    }

    public function testReportsUnknownServer()
    {
        $tester = $this->createTester(['research' => ['github' => new InMemoryTransport()]]);

        $this->assertSame(Command::INVALID, $tester->execute(['client' => 'research', 'server' => 'gitlab']));
        $this->assertStringContainsString('has no server named "gitlab". Configured:', $tester->getDisplay());
    }

    public function testReportsConnectionFailureAndClosesTheConnection()
    {
        $transport = new InMemoryTransport(failOnConnect: true);
        $tester = $this->createTester(['research' => ['github' => $transport]]);

        $this->assertSame(Command::FAILURE, $tester->execute(['client' => 'research', 'server' => 'github']));
        $this->assertStringContainsString('Failed to connect MCP client "research" to server "github"', $tester->getDisplay());
    }

    public function testDisconnectsAfterDescribing()
    {
        $transport = $this->populatedTransport();
        $tester = $this->createTester(['research' => ['github' => $transport]]);

        $tester->execute(['client' => 'research', 'server' => 'github']);

        $this->assertSame(1, $transport->closeCount);
    }

    public function testCompletesClientsAndTheirServers()
    {
        $clients = ['research' => ['github' => new InMemoryTransport(), 'filesystem' => new InMemoryTransport()], 'simple' => ['github' => new InMemoryTransport()]];

        $tester = new CommandCompletionTester($this->createCommand($clients));

        $this->assertSame(['research', 'simple'], $tester->complete(['']));
        $this->assertSame(['github', 'filesystem'], $tester->complete(['research', '']));
    }

    /**
     * @param array<string, array<string, InMemoryTransport>> $clients
     */
    private function createTester(array $clients): CommandTester
    {
        return new CommandTester($this->createCommand($clients));
    }

    /**
     * @param array<string, array<string, InMemoryTransport>> $clients
     */
    private function createCommand(array $clients): Command
    {
        $services = [];
        foreach ($clients as $clientName => $servers) {
            $connections = [];
            foreach ($servers as $serverName => $transport) {
                $connections[$serverName] = static fn (): ServerConnection => new ServerConnection($clientName, $serverName, Client::builder()->build(), $transport);
            }

            $services[$clientName] = static fn (): McpClientInterface => new McpClient($clientName, new ServiceLocator($connections));
        }

        $command = new Command('mcp:client:debug');
        $command->setCode(new ClientDebugCommand(new ServiceLocator($services)));

        return $command;
    }

    private function populatedTransport(): InMemoryTransport
    {
        return new InMemoryTransport([
            'tools/list' => [['tools' => [[
                'name' => 'read_file',
                'description' => 'Read a file from disk.',
                'inputSchema' => ['type' => 'object', 'properties' => ['path' => ['type' => 'string']], 'required' => ['path']],
            ]]]],
        ]);
    }
}
