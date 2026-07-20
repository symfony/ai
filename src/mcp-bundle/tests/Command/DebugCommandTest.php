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

use Mcp\Capability\Registry;
use Mcp\Schema\Prompt;
use Mcp\Schema\PromptArgument;
use Mcp\Schema\ResourceDefinition;
use Mcp\Schema\ResourceTemplate;
use Mcp\Schema\Tool;
use Mcp\Server;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\Command\DebugCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

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
        $this->assertStringContainsString('registered as services with autoconfiguration enabled', $display);
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

    private function createTester(Registry $registry): CommandTester
    {
        $command = new Command('debug:mcp');
        $command->setCode(new DebugCommand(Server::builder()->setRegistry($registry), $registry));

        return new CommandTester($command);
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
