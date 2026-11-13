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

use Mcp\Client\Client;
use Mcp\Schema\Prompt;
use Mcp\Schema\PromptArgument;
use Mcp\Schema\ResourceDefinition;
use Mcp\Schema\ResourceTemplate;
use Mcp\Schema\Tool;
use Mcp\Server\Builder;
use Mcp\Server\Capability\Registry;
use Mcp\Server\Server;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\Client\McpClient;
use Symfony\AI\McpBundle\Client\McpClientInterface;
use Symfony\AI\McpBundle\Client\ServerConnection;
use Symfony\AI\McpBundle\Command\DebugCommand;
use Symfony\AI\McpBundle\Tests\Double\InMemoryTransport;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class DebugCommandTest extends TestCase
{
    public function testListsRegisteredCapabilities()
    {
        $tester = $this->createTester($this->createPopulatedRegistry());

        $exitCode = $tester->execute([]);
        $display = $tester->getDisplay();

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Tools (1)', $display);
        $this->assertStringContainsString('current-time', $display);
        $this->assertStringContainsString('App\TimeTool::getCurrentTime()', $display);
        $this->assertStringContainsString('Prompts (1)', $display);
        $this->assertStringContainsString('greeting', $display);
        $this->assertStringContainsString('Resources (1)', $display);
        $this->assertStringContainsString('config://app', $display);
        $this->assertStringContainsString('Resource Templates (1)', $display);
        $this->assertStringContainsString('user://{id}', $display);
    }

    public function testWarnsWithHintWhenNothingIsRegistered()
    {
        $tester = $this->createTester(new Registry());

        $exitCode = $tester->execute([]);
        $display = $tester->getDisplay();

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('No MCP capabilities are registered.', $display);
        $this->assertStringContainsString('autoconfiguration enabled', $display);
    }

    public function testDescribesToolWithInputSchema()
    {
        $tester = $this->createTester($this->createPopulatedRegistry());

        $exitCode = $tester->execute(['name' => 'current-time']);
        $display = $tester->getDisplay();

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Tool "current-time"', $display);
        $this->assertStringContainsString('App\TimeTool::getCurrentTime()', $display);
        $this->assertStringContainsString('Input Schema', $display);
        $this->assertStringContainsString('"format"', $display);
    }

    public function testDescribesResourceByUri()
    {
        $tester = $this->createTester($this->createPopulatedRegistry());

        $exitCode = $tester->execute(['name' => 'config://app']);
        $display = $tester->getDisplay();

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Resource "config://app"', $display);
        $this->assertStringContainsString('application/json', $display);
    }

    public function testFailsForUnknownName()
    {
        $tester = $this->createTester($this->createPopulatedRegistry());

        $exitCode = $tester->execute(['name' => 'unknown-element']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('No MCP capability named "unknown-element" is registered.', $tester->getDisplay());
    }

    public function testListsEveryServerSeparately()
    {
        $tester = $this->createTester(['public' => new Registry(), 'editors' => $this->createPopulatedRegistry()]);

        $this->assertSame(Command::SUCCESS, $tester->execute([]));

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Server "public"', $display);
        $this->assertStringContainsString('Server "editors"', $display);
        $this->assertStringContainsString('current-time', $display);
    }

    public function testRestrictsOutputToOneServer()
    {
        $tester = $this->createTester(['public' => new Registry(), 'editors' => $this->createPopulatedRegistry()]);

        $this->assertSame(Command::SUCCESS, $tester->execute(['--server' => 'editors']));

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Server "editors"', $display);
        $this->assertStringNotContainsString('Server "public"', $display);
    }

    public function testReportsUnknownServer()
    {
        $tester = $this->createTester(['editors' => $this->createPopulatedRegistry()]);

        $this->assertSame(Command::INVALID, $tester->execute(['--server' => 'missing']));
        $this->assertStringContainsString('No MCP server named "missing" is configured.', $tester->getDisplay());
        $this->assertStringContainsString('Available: editors.', $tester->getDisplay());
    }

    public function testDescribingAnElementNamesTheServerExposingIt()
    {
        $tester = $this->createTester(['public' => new Registry(), 'editors' => $this->createPopulatedRegistry()]);

        $this->assertSame(Command::SUCCESS, $tester->execute(['name' => 'current-time']));
        $this->assertStringContainsString('Tool "current-time" on server "editors"', $tester->getDisplay());
    }

    public function testListsConfiguredClientsWithoutConnecting()
    {
        $transport = $this->populatedTransport();
        $tester = $this->createTester(new Registry(), ['research' => ['github' => $transport, 'filesystem' => new InMemoryTransport()]]);

        $this->assertSame(Command::SUCCESS, $tester->execute(['--clients' => true]));

        $display = $tester->getDisplay();
        $this->assertStringContainsString('research', $display);
        $this->assertStringContainsString('github, filesystem', $display);
        // Asking which clients exist must not spawn processes or open sessions.
        $this->assertSame(0, $transport->connectCount);
    }

    public function testDefaultOutputSummarizesClientsWithoutConnecting()
    {
        $transport = $this->populatedTransport();
        $tester = $this->createTester($this->createPopulatedRegistry(), ['research' => ['github' => $transport]]);

        $this->assertSame(Command::SUCCESS, $tester->execute([]));

        $display = $tester->getDisplay();
        // Both sides of the bundle in one command.
        $this->assertStringContainsString('current-time', $display);
        $this->assertStringContainsString('Clients (1)', $display);
        $this->assertSame(0, $transport->connectCount);
    }

    public function testWarnsWhenNoClientIsConfigured()
    {
        $tester = $this->createTester(new Registry());

        $this->assertSame(Command::SUCCESS, $tester->execute(['--clients' => true]));
        $this->assertStringContainsString('No MCP client is configured.', $tester->getDisplay());
    }

    public function testDescribesARemoteServer()
    {
        $tester = $this->createTester(new Registry(), ['research' => ['github' => $this->populatedTransport()]]);

        $this->assertSame(Command::SUCCESS, $tester->execute(['--client' => 'research', '--server' => 'github']));

        $display = $tester->getDisplay();
        $this->assertStringContainsString('MCP client "research" → server "github"', $display);
        $this->assertStringContainsString('test-server', $display);
        $this->assertStringContainsString('Be nice.', $display);
        $this->assertStringContainsString('Tools (1)', $display);
        $this->assertStringContainsString('read_file', $display);
        $this->assertStringContainsString('Read a file from disk.', $display);
    }

    public function testSingleServerClientNeedsNoServerOption()
    {
        $tester = $this->createTester(new Registry(), ['research' => ['github' => $this->populatedTransport()]]);

        $this->assertSame(Command::SUCCESS, $tester->execute(['--client' => 'research']));
        $this->assertStringContainsString('server "github"', $tester->getDisplay());
    }

    public function testMultiServerClientRequiresTheServerOption()
    {
        $tester = $this->createTester(new Registry(), ['research' => ['github' => new InMemoryTransport(), 'filesystem' => new InMemoryTransport()]]);

        $this->assertSame(Command::INVALID, $tester->execute(['--client' => 'research']));
        $this->assertStringContainsString('connects to several servers', $tester->getDisplay());
    }

    public function testReportsUnknownClient()
    {
        $tester = $this->createTester(new Registry(), ['research' => ['github' => new InMemoryTransport()]]);

        $this->assertSame(Command::INVALID, $tester->execute(['--client' => 'missing']));
        $this->assertStringContainsString('No MCP client named "missing" is configured.', $tester->getDisplay());
        $this->assertStringContainsString('research', $tester->getDisplay());
    }

    public function testReportsUnknownRemoteServer()
    {
        $tester = $this->createTester(new Registry(), ['research' => ['github' => new InMemoryTransport()]]);

        $this->assertSame(Command::INVALID, $tester->execute(['--client' => 'research', '--server' => 'gitlab']));
        $this->assertStringContainsString('has no server named "gitlab"', $tester->getDisplay());
    }

    public function testReportsRemoteConnectionFailure()
    {
        $tester = $this->createTester(new Registry(), ['research' => ['github' => new InMemoryTransport(failOnConnect: true)]]);

        $this->assertSame(Command::FAILURE, $tester->execute(['--client' => 'research', '--server' => 'github']));
        $this->assertStringContainsString('Failed to connect MCP client "research" to server "github"', $tester->getDisplay());
    }

    public function testDisconnectsAfterDescribingARemoteServer()
    {
        $transport = $this->populatedTransport();
        $tester = $this->createTester(new Registry(), ['research' => ['github' => $transport]]);

        $tester->execute(['--client' => 'research', '--server' => 'github']);

        $this->assertSame(1, $transport->closeCount);
    }

    public function testCompletesServersAndClients()
    {
        $tester = new CommandCompletionTester($this->createCommand(
            ['public' => new Registry(), 'editors' => new Registry()],
            ['research' => ['github' => new InMemoryTransport()], 'simple' => ['github' => new InMemoryTransport()]],
        ));

        $this->assertSame(['public', 'editors'], $tester->complete(['--server', '']));
        $this->assertSame(['research', 'simple'], $tester->complete(['--client', '']));
    }

    /**
     * @param array<string, Registry>                         $registries
     * @param array<string, array<string, InMemoryTransport>> $clients
     */
    private function createTester(Registry|array $registries, array $clients = []): CommandTester
    {
        return new CommandTester($this->createCommand($registries, $clients));
    }

    /**
     * @param array<string, Registry>                         $registries
     * @param array<string, array<string, InMemoryTransport>> $clients
     */
    private function createCommand(Registry|array $registries, array $clients = []): Command
    {
        $registries = $registries instanceof Registry ? ['default' => $registries] : $registries;

        $clientServices = [];
        foreach ($clients as $clientName => $servers) {
            $connections = [];
            foreach ($servers as $serverName => $transport) {
                $connections[$serverName] = static fn (): ServerConnection => new ServerConnection($clientName, $serverName, Client::builder()->build(), $transport);
            }

            $clientServices[$clientName] = static fn (): McpClientInterface => new McpClient($clientName, new ServiceLocator($connections));
        }

        $command = new Command('debug:mcp');
        $command->setCode(new DebugCommand(
            new ServiceLocator(array_map(
                static fn (Registry $registry): \Closure => static fn (): Builder => Server::builder()->setRegistry($registry),
                $registries,
            )),
            new ServiceLocator(array_map(
                static fn (Registry $registry): \Closure => static fn (): Registry => $registry,
                $registries,
            )),
            new ServiceLocator($clientServices),
        ));

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

    private function createPopulatedRegistry(): Registry
    {
        $registry = new Registry();

        $registry->registerTool(new Tool(
            name: 'current-time',
            title: 'Current Time',
            inputSchema: ['type' => 'object', 'properties' => ['format' => ['type' => 'string']], 'required' => ['format']],
            description: 'Returns the current time',
            annotations: null,
        ), ['App\TimeTool', 'getCurrentTime']);

        $registry->registerPrompt(new Prompt(
            name: 'greeting',
            description: 'A greeting prompt',
            arguments: [new PromptArgument('name', 'The name to greet', true)],
        ), ['App\GreetingPrompt', 'greeting']);

        $registry->registerResource(new ResourceDefinition(
            uri: 'config://app',
            name: 'app-config',
            mimeType: 'application/json',
        ), ['App\ConfigResource', 'read']);

        $registry->registerResourceTemplate(new ResourceTemplate(
            uriTemplate: 'user://{id}',
            name: 'user',
        ), ['App\UserTemplate', 'read']);

        return $registry;
    }
}
