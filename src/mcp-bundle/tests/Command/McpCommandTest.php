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

use Mcp\Server;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\Command\McpCommand;
use Symfony\AI\McpBundle\Exception\RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class McpCommandTest extends TestCase
{
    public function testRequiresTheNameWithSeveralStdioServers()
    {
        $tester = $this->createTester(['default', 'editors']);

        $this->assertSame(Command::INVALID, $tester->execute([]));
        $this->assertStringContainsString('Several MCP servers expose the STDIO transport, name the one to run', $tester->getDisplay());
    }

    public function testReportsUnknownServer()
    {
        $tester = $this->createTester(['editors']);

        $this->assertSame(Command::INVALID, $tester->execute(['name' => 'missing']));
        $this->assertStringContainsString('No MCP server named "missing" exposes the STDIO transport.', $tester->getDisplay());
        $this->assertStringContainsString('editors.', $tester->getDisplay());
    }

    public function testRunsTheNamedServer()
    {
        $tester = $this->createTester(['editors', 'public']);

        // Mcp\Server is final, so the locator reports which server was picked instead of running it —
        // running would hand STDIN and STDOUT to a real transport.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('resolved server "editors"');

        $tester->execute(['name' => 'editors']);
    }

    public function testRunsTheOnlyStdioServerWithoutAnArgument()
    {
        $tester = $this->createTester(['editors']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('resolved server "editors"');

        $tester->execute([]);
    }

    public function testCompletesServerNames()
    {
        $tester = new CommandCompletionTester($this->createCommand(['public', 'editors']));

        $this->assertSame(['public', 'editors'], $tester->complete(['']));
    }

    /**
     * @param list<string> $servers
     */
    private function createTester(array $servers): CommandTester
    {
        return new CommandTester($this->createCommand($servers));
    }

    /**
     * @param list<string> $servers
     */
    private function createCommand(array $servers): Command
    {
        $factories = [];
        foreach ($servers as $name) {
            $factories[$name] = static function () use ($name): Server {
                throw new RuntimeException(\sprintf('resolved server "%s"', $name));
            };
        }

        $command = new Command('mcp:server');
        $command->setCode(new McpCommand(new ServiceLocator($factories)));

        return $command;
    }
}
