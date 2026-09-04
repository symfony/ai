<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Tests\Command;

use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\Mate\Capability\ServerInfo;
use Symfony\AI\Mate\Command\ToolsCallCommand;
use Symfony\AI\Mate\Discovery\CapabilityRegistry;
use Symfony\AI\Mate\Discovery\ReflectionDiscoverer;
use Symfony\AI\Mate\Exception\InvalidArgumentException;
use Symfony\AI\Mate\Invocation\HandlerInvoker;
use Symfony\AI\Mate\Invocation\ToolInvoker;
use Symfony\AI\Mate\Tests\Command\Fixtures\SampleTool;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ToolsCallCommandTest extends TestCase
{
    public function testExecuteCallsToolSuccessfully()
    {
        $tester = new CommandTester($this->createServerInfoCommand());

        $tester->execute(['tool-name' => 'server-info']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Executing Tool: server-info', $output);
        $this->assertStringContainsString('Result', $output);
        $this->assertStringContainsString(\PHP_VERSION, $output);
    }

    public function testExecuteWithJsonFormat()
    {
        $tester = new CommandTester($this->createServerInfoCommand());

        $tester->execute(['tool-name' => 'server-info', '--format' => 'json']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();

        // JSON format should not include decorative headers
        $this->assertStringNotContainsString('Executing Tool:', $output);

        $result = json_decode($output, true);
        $this->assertIsArray($result);
        $this->assertSame(\PHP_VERSION, $result['php_version']);
    }

    public function testExecuteWithToonFormat()
    {
        $tester = new CommandTester($this->createServerInfoCommand());

        $tester->execute(['tool-name' => 'server-info', '--format' => 'toon']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();

        $result = Toon::decode($output);
        $this->assertIsArray($result);
        $this->assertSame(\PHP_VERSION, $result['php_version']);
    }

    public function testExecuteWithInvalidToolName()
    {
        $tester = new CommandTester($this->createServerInfoCommand());

        $tester->execute(['tool-name' => 'non-existent-tool']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Tool "non-existent-tool" not found', $output);
    }

    public function testExecuteWithInvalidJson()
    {
        $tester = new CommandTester($this->createServerInfoCommand());

        $tester->execute(['tool-name' => 'server-info', '--json' => '{invalid json}']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Invalid JSON', $output);
    }

    public function testExecuteWithJsonParameters()
    {
        $tester = new CommandTester($this->createSampleToolCommand());

        $tester->execute([
            'tool-name' => 'sample-add',
            '--json' => '{"a": 4, "b": 1}',
            '--format' => 'json',
        ]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $result = json_decode($tester->getDisplay(), true);
        $this->assertSame(5, $result['sum']);
    }

    public function testDynamicOptionsAreCoercedFromArgv()
    {
        $output = $this->runViaArgv(['sample-add', '--a=2', '--b=3', '--format=json']);

        $result = json_decode($output, true);
        $this->assertIsArray($result);
        $this->assertSame(5, $result['sum']);
    }

    public function testDynamicBooleanFlagFromArgv()
    {
        $output = $this->runViaArgv(['sample-add', '--a=2', '--b=3', '--negate', '--format=json']);

        $result = json_decode($output, true);
        $this->assertIsArray($result);
        $this->assertSame(-5, $result['sum']);
    }

    public function testDynamicOptionsWithSeparatedValueFromArgv()
    {
        $output = $this->runViaArgv(['sample-add', '--a', '10', '--format', 'json']);

        $result = json_decode($output, true);
        $this->assertIsArray($result);
        $this->assertSame(10, $result['sum']);
    }

    public function testNegativeSeparatedValueIsNotMistakenForAFlag()
    {
        $output = $this->runViaArgv(['sample-add', '--a', '-5', '--format=json']);

        $result = json_decode($output, true);
        $this->assertIsArray($result);
        $this->assertSame(-5, $result['sum']);
    }

    public function testValueTakingOptionWithoutAValueIsReported()
    {
        $output = $this->runViaArgv(['sample-add', '--a=1', '--b', '--negate', '--format=json']);

        $this->assertStringContainsString('"--b" option requires a value', $output);
    }

    public function testReservedOptionDoesNotSwallowTheNextOption()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "--format" option requires a value.');

        $this->runViaArgv(['sample-add', '--format', '--a=1']);
    }

    public function testEndOfOptionsSeparatorIsIgnored()
    {
        $output = $this->runViaArgv(['--', 'sample-add', '--a=4', '--format=json']);

        $result = json_decode($output, true);
        $this->assertIsArray($result);
        $this->assertSame(4, $result['sum']);
    }

    public function testToolNameMayFollowAnOption()
    {
        $output = $this->runViaArgv(['--a=7', 'sample-add', '--format=json']);

        $result = json_decode($output, true);
        $this->assertIsArray($result);
        $this->assertSame(7, $result['sum']);
    }

    public function testUnknownParameterIsReported()
    {
        $output = $this->runViaArgv(['sample-add', '--a=1', '--nope=2', '--format=json']);

        $this->assertStringContainsString('Unknown argument "nope"', $output);
    }

    /**
     * A wrong or missing parameter name is a guess the caller can correct right away, if it
     * knows where the schema is; point at tools:inspect instead of leaving it a guess.
     */
    public function testUnknownParameterPointsAtToolsInspect()
    {
        $output = $this->runViaArgv(['sample-add', '--a=1', '--nope=2', '--format=json']);

        $this->assertStringContainsString('tools:inspect sample-add', $output);
    }

    public function testMissingRequiredParameterPointsAtToolsInspect()
    {
        $output = $this->runViaArgv(['sample-add', '--format=json']);

        $this->assertStringContainsString('Missing required argument "a"', $output);
        $this->assertStringContainsString('tools:inspect sample-add', $output);
    }

    /**
     * `--silent` suppresses all output, so treating it as a tool parameter turned a rejected
     * unknown argument into a run that printed nothing and failed.
     */
    public function testGlobalSilentFlagIsNotTakenForAToolParameter()
    {
        $output = $this->runViaArgv(['sample-add', '--a=2', '--b=3', '--silent', '--format=json']);

        $this->assertStringNotContainsString('Unknown argument', $output);
    }

    public function testRejectedValueNamesTheOptionItCameFrom()
    {
        $output = $this->runViaArgv(['sample-add', '--a=nope', '--format=json']);

        $this->assertStringContainsString('Invalid value "nope" for "--a"', $output);
    }

    public function testPlainTextResultIsNotTreatedAsAnEncodedPayload()
    {
        $output = $this->runViaArgv(['sample-echo', '--text=hello world', '--format=json']);

        $this->assertSame('hello world', json_decode($output, true));
    }

    /**
     * A tool returning the string "42" must not turn into the number 42 on the way out.
     */
    public function testNumericTextResultKeepsItsType()
    {
        $output = $this->runViaArgv(['sample-echo', '--text=42', '--format=json']);

        $this->assertSame('42', json_decode($output, true));
    }

    public function testRepeatedOptionFeedsAVariadicParameter()
    {
        $output = $this->runViaArgv(['sample-tags', '--sku=A', '--tags=red', '--tags=blue', '--format=json']);

        $result = json_decode($output, true);
        $this->assertIsArray($result);
        $this->assertSame(['red', 'blue'], $result['tags']);
    }

    public function testRepeatedOptionOnASingleValueParameterIsReported()
    {
        $output = $this->runViaArgv(['sample-tags', '--sku=A', '--sku=B', '--format=json']);

        $this->assertStringContainsString('takes a single value, but a list of 2 was', $output);
    }

    /**
     * Runs the command with a real ArgvInput so the dynamic `--<param>` parsing is exercised.
     *
     * @param list<string> $arguments the tool name followed by its option tokens
     */
    private function runViaArgv(array $arguments): string
    {
        $command = $this->createSampleToolCommand();

        // The first token stands in for the command name that Application would strip
        // via ArgvInput::getRawTokens(true); the remaining tokens are the tool arguments.
        $input = new ArgvInput(array_merge(['mate', 'tools:call'], $arguments));
        $output = new BufferedOutput();
        $command->run($input, $output);

        return $output->fetch();
    }

    private function createServerInfoCommand(): ToolsCallCommand
    {
        $container = new ContainerBuilder();
        $container->set(ServerInfo::class, new ServerInfo());

        return $this->createCommand(__DIR__.'/../..', ['_custom' => ['dirs' => ['src/Capability'], 'includes' => []]], $container);
    }

    private function createSampleToolCommand(): ToolsCallCommand
    {
        $container = new ContainerBuilder();
        $container->set(SampleTool::class, new SampleTool());

        return $this->createCommand(__DIR__.'/../..', ['_custom' => ['dirs' => ['tests/Command/Fixtures'], 'includes' => []]], $container);
    }

    /**
     * @param array<string, array{dirs: string[], includes: string[]}> $extensions
     */
    private function createCommand(string $rootDir, array $extensions, ContainerBuilder $container): ToolsCallCommand
    {
        $logger = new NullLogger();
        $discoverer = new ReflectionDiscoverer($logger);
        $registry = new CapabilityRegistry($rootDir, $extensions, [], $discoverer, $logger);
        $invoker = new ToolInvoker(new HandlerInvoker($container));

        return new class($registry, $invoker) extends ToolsCallCommand {
            protected function isToonFormatAvailable(): bool
            {
                return true;
            }
        };
    }
}
