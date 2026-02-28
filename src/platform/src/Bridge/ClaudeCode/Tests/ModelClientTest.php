<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ClaudeCode\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\ClaudeCode\ClaudeCode;
use Symfony\AI\Platform\Bridge\ClaudeCode\Exception\CliNotFoundException;
use Symfony\AI\Platform\Bridge\ClaudeCode\ModelClient;
use Symfony\AI\Platform\Bridge\ClaudeCode\RawProcessResult;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ModelClientTest extends TestCase
{
    public function testSupportsClaudeCode()
    {
        $client = new ModelClient();

        $this->assertTrue($client->supports(new ClaudeCode('sonnet')));
    }

    public function testDoesNotSupportOtherModels()
    {
        $client = new ModelClient();

        $this->assertFalse($client->supports(new Claude('claude-3-5-sonnet-latest')));
    }

    public function testThrowsExceptionWhenCliNotFound()
    {
        $client = new ModelClient('/non/existent/binary/that/does/not/exist');

        $this->expectException(CliNotFoundException::class);
        $this->expectExceptionMessage('The "claude" CLI binary was not found.');

        $client->buildCommand('Hello');
    }

    public function testBuildCommandWithDefaults()
    {
        $client = new ModelClient('/usr/bin/echo');

        $command = $client->buildCommand('Hello, World!');

        $this->assertSame(['/usr/bin/echo', '--output-format', 'stream-json', '--verbose', '--include-partial-messages', '-p', 'Hello, World!'], $command);
    }

    public function testBuildCommandWithSystemPrompt()
    {
        $client = new ModelClient('/usr/bin/echo');

        $command = $client->buildCommand('Hello', ['system_prompt' => 'You are a pirate']);

        $this->assertContains('--system-prompt', $command);
        $this->assertContains('You are a pirate', $command);
    }

    public function testBuildCommandWithModel()
    {
        $client = new ModelClient('/usr/bin/echo');

        $command = $client->buildCommand('Hello', ['model' => 'sonnet']);

        $this->assertContains('--model', $command);
        $this->assertContains('sonnet', $command);
    }

    public function testBuildCommandWithMaxTurns()
    {
        $client = new ModelClient('/usr/bin/echo');

        $command = $client->buildCommand('Hello', ['max_turns' => 3]);

        $this->assertContains('--max-turns', $command);
        $this->assertContains('3', $command);
    }

    public function testBuildCommandWithPermissionMode()
    {
        $client = new ModelClient('/usr/bin/echo');

        $command = $client->buildCommand('Hello', ['permission_mode' => 'plan']);

        $this->assertContains('--permission-mode', $command);
        $this->assertContains('plan', $command);
    }

    public function testBuildCommandWithAllowedTools()
    {
        $client = new ModelClient('/usr/bin/echo');

        $command = $client->buildCommand('Hello', ['allowed_tools' => ['Bash', 'Read']]);

        $this->assertSame(2, \count(array_keys($command, '--allowedTools', true)));
        $this->assertContains('Bash', $command);
        $this->assertContains('Read', $command);
    }

    public function testBuildCommandWithMcpConfig()
    {
        $client = new ModelClient('/usr/bin/echo');

        $command = $client->buildCommand('Hello', ['mcp_config' => '/path/to/config.json']);

        $this->assertContains('--mcp-config', $command);
        $this->assertContains('/path/to/config.json', $command);
    }

    public function testBuildCommandWithAllOptions()
    {
        $client = new ModelClient('/usr/bin/echo');

        $command = $client->buildCommand('Hello', [
            'system_prompt' => 'Be helpful',
            'model' => 'opus',
            'max_turns' => 5,
            'permission_mode' => 'plan',
            'allowed_tools' => ['Bash'],
            'mcp_config' => '/path/to/config.json',
        ]);

        $expected = [
            '/usr/bin/echo',
            '--output-format', 'stream-json', '--verbose', '--include-partial-messages',
            '--system-prompt', 'Be helpful',
            '--model', 'opus',
            '--max-turns', '5',
            '--permission-mode', 'plan',
            '--allowedTools', 'Bash',
            '--mcp-config', '/path/to/config.json',
            '-p', 'Hello',
        ];

        $this->assertSame($expected, $command);
    }

    public function testBuildCommandRewritesToolsToAllowedTools()
    {
        $client = new ModelClient('/usr/bin/echo');

        $command = $client->buildCommand('Hello', ['tools' => ['Bash', 'Read']]);

        $this->assertNotContains('--tools', $command);
        $this->assertSame(2, \count(array_keys($command, '--allowedTools', true)));
        $this->assertContains('Bash', $command);
        $this->assertContains('Read', $command);
    }

    public function testBuildCommandPassesUnknownOptionsAsFlags()
    {
        $client = new ModelClient('/usr/bin/echo');

        $command = $client->buildCommand('Hello', ['custom_flag' => 'value']);

        $this->assertContains('--custom-flag', $command);
        $this->assertContains('value', $command);
    }

    public function testBuildCommandHandlesBooleanTrueAsFlag()
    {
        $client = new ModelClient('/usr/bin/echo');

        $command = $client->buildCommand('Hello', ['no_cache' => true]);

        $this->assertContains('--no-cache', $command);
    }

    public function testBuildCommandSkipsBooleanFalse()
    {
        $client = new ModelClient('/usr/bin/echo');

        $command = $client->buildCommand('Hello', ['no_cache' => false]);

        $this->assertNotContains('--no-cache', $command);
    }

    public function testRequestReturnsRawProcessResult()
    {
        $client = new ModelClient('/usr/bin/echo');
        $model = new ClaudeCode('sonnet');

        $result = $client->request($model, 'Hello');

        $this->assertInstanceOf(RawProcessResult::class, $result);
    }

    public function testRequestPassesModelNameToCommand()
    {
        $client = new ModelClient('/usr/bin/echo');
        $model = new ClaudeCode('opus');

        $result = $client->request($model, 'Hello');
        $commandLine = $result->getObject()->getCommandLine();

        $this->assertStringContainsString('--model', $commandLine);
        $this->assertStringContainsString('opus', $commandLine);
    }

    public function testRequestUsesPromptFromNormalizedPayload()
    {
        $client = new ModelClient('/usr/bin/echo');
        $model = new ClaudeCode('sonnet');

        $payload = ['prompt' => 'What is PHP?', 'system_prompt' => 'You are helpful.'];

        $result = $client->request($model, $payload);
        $commandLine = $result->getObject()->getCommandLine();

        $this->assertStringContainsString('What is PHP?', $commandLine);
        $this->assertStringContainsString('--system-prompt', $commandLine);
        $this->assertStringContainsString('You are helpful.', $commandLine);
    }

    public function testRequestUsesStringPayloadDirectly()
    {
        $client = new ModelClient('/usr/bin/echo');
        $model = new ClaudeCode('sonnet');

        $result = $client->request($model, 'Hello, World!');
        $commandLine = $result->getObject()->getCommandLine();

        $this->assertStringContainsString('Hello, World!', $commandLine);
    }
}
