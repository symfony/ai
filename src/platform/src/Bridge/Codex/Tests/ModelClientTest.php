<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Codex\Tests;

use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Codex\Codex;
use Symfony\AI\Platform\Bridge\Codex\Exception\CliNotFoundException;
use Symfony\AI\Platform\Bridge\Codex\ModelClient;
use Symfony\AI\Platform\Bridge\Codex\RawProcessResult;
use Symfony\AI\Platform\Model;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ModelClientTest extends TestCase
{
    private static string $binary;

    public static function setUpBeforeClass(): void
    {
        self::$binary = file_exists('/usr/bin/echo') ? '/usr/bin/echo' : '/bin/echo';
    }

    public function testSupportsCodex()
    {
        $client = new ModelClient();

        $this->assertTrue($client->supports(new Codex('gpt-5-codex')));
    }

    public function testDoesNotSupportOtherModels()
    {
        $client = new ModelClient();

        $this->assertFalse($client->supports(new Model('sonnet')));
    }

    public function testThrowsExceptionWhenCliNotFound()
    {
        $client = new ModelClient('/non/existent/binary/that/does/not/exist');

        $this->expectException(CliNotFoundException::class);
        $this->expectExceptionMessage('The "codex" CLI binary was not found.');

        $client->buildCommand('Hello');
    }

    public function testBuildCommandWithDefaults()
    {
        $client = new ModelClient(self::$binary);

        $command = $client->buildCommand('Hello, World!');

        $this->assertSame([self::$binary, 'exec', '--json', 'Hello, World!'], $command);
    }

    public function testBuildCommandWithModel()
    {
        $client = new ModelClient(self::$binary);

        $command = $client->buildCommand('Hello', ['model' => 'gpt-5-codex']);

        $this->assertContains('--model', $command);
        $this->assertContains('gpt-5-codex', $command);
    }

    public function testBuildCommandWithSandbox()
    {
        $client = new ModelClient(self::$binary);

        $command = $client->buildCommand('Hello', ['sandbox' => 'workspace-write']);

        $this->assertContains('--sandbox', $command);
        $this->assertContains('workspace-write', $command);
    }

    public function testBuildCommandWithImage()
    {
        $client = new ModelClient(self::$binary);

        $command = $client->buildCommand('Hello', ['image' => '/path/to/image.png']);

        $this->assertContains('--image', $command);
        $this->assertContains('/path/to/image.png', $command);
    }

    public function testBuildCommandWithAllowedTools()
    {
        $client = new ModelClient(self::$binary);

        $command = $client->buildCommand('Hello', ['allowed_tools' => ['Bash', 'Read']]);

        $this->assertSame(2, \count(array_keys($command, '--allowedTools', true)));
        $this->assertContains('Bash', $command);
        $this->assertContains('Read', $command);
    }

    public function testBuildCommandWithAllOptions()
    {
        $client = new ModelClient(self::$binary);

        $command = $client->buildCommand('Hello', [
            'model' => 'gpt-5-codex',
            'sandbox' => 'workspace-write',
            'allowed_tools' => ['Bash'],
        ]);

        $expected = [
            self::$binary,
            'exec', '--json',
            '--model', 'gpt-5-codex',
            '--sandbox', 'workspace-write',
            '--allowedTools', 'Bash',
            'Hello',
        ];

        $this->assertSame($expected, $command);
    }

    #[IgnoreDeprecations]
    public function testBuildCommandTriggersDeprecationForAskForApproval()
    {
        $client = new ModelClient(self::$binary);

        $this->expectUserDeprecationMessageMatches('/ask_for_approval/');

        $command = $client->buildCommand('Hello', ['ask_for_approval' => 'on-request']);

        $this->assertNotContains('--ask-for-approval', $command);
        $this->assertSame([self::$binary, 'exec', '--json', 'Hello'], $command);
    }

    public function testBuildCommandWithDangerouslyBypassApprovalsAndSandbox()
    {
        $client = new ModelClient(self::$binary);

        $command = $client->buildCommand('Hello', [
            'dangerously_bypass_approvals_and_sandbox' => true,
        ]);

        $this->assertSame([
            self::$binary,
            'exec', '--json',
            '--dangerously-bypass-approvals-and-sandbox',
            'Hello',
        ], $command);
    }

    public function testBuildCommandRewritesToolsToAllowedTools()
    {
        $client = new ModelClient(self::$binary);

        $command = $client->buildCommand('Hello', ['tools' => ['Bash', 'Read']]);

        $this->assertNotContains('--tools', $command);
        $this->assertSame(2, \count(array_keys($command, '--allowedTools', true)));
        $this->assertContains('Bash', $command);
        $this->assertContains('Read', $command);
    }

    public function testBuildCommandPassesUnknownOptionsAsFlags()
    {
        $client = new ModelClient(self::$binary);

        $command = $client->buildCommand('Hello', ['custom_flag' => 'value']);

        $this->assertContains('--custom-flag', $command);
        $this->assertContains('value', $command);
    }

    public function testBuildCommandHandlesBooleanTrueAsFlag()
    {
        $client = new ModelClient(self::$binary);

        $command = $client->buildCommand('Hello', ['search' => true]);

        $this->assertContains('--search', $command);
    }

    public function testBuildCommandSkipsBooleanFalse()
    {
        $client = new ModelClient(self::$binary);

        $command = $client->buildCommand('Hello', ['search' => false]);

        $this->assertNotContains('--search', $command);
    }

    public function testRequestReturnsRawProcessResult()
    {
        $client = new ModelClient(self::$binary);
        $model = new Codex('gpt-5-codex');

        $result = $client->request($model, 'Hello');

        $this->assertInstanceOf(RawProcessResult::class, $result);
    }

    public function testRequestPassesModelNameToCommand()
    {
        $client = new ModelClient(self::$binary);
        $model = new Codex('gpt-5-codex');

        $result = $client->request($model, 'Hello');
        $commandLine = $result->getObject()->getCommandLine();

        $this->assertStringContainsString('--model', $commandLine);
        $this->assertStringContainsString('gpt-5-codex', $commandLine);
    }

    public function testRequestUsesPromptFromNormalizedPayload()
    {
        $client = new ModelClient(self::$binary);
        $model = new Codex('gpt-5-codex');

        $payload = ['prompt' => 'What is PHP?', 'system_prompt' => 'You are helpful.'];

        $result = $client->request($model, $payload);
        $commandLine = $result->getObject()->getCommandLine();

        $this->assertStringContainsString('What is PHP?', $commandLine);
        $this->assertStringContainsString('You are helpful.', $commandLine);
    }

    public function testRequestUsesStringPayloadDirectly()
    {
        $client = new ModelClient(self::$binary);
        $model = new Codex('gpt-5-codex');

        $result = $client->request($model, 'Hello, World!');
        $commandLine = $result->getObject()->getCommandLine();

        $this->assertStringContainsString('Hello, World!', $commandLine);
    }

    public function testRequestAddsDefaultSandbox()
    {
        $client = new ModelClient(self::$binary);
        $model = new Codex('gpt-5-codex');

        $result = $client->request($model, 'Hello');
        $commandLine = $result->getObject()->getCommandLine();

        $this->assertStringContainsString('--sandbox', $commandLine);
        $this->assertStringContainsString('read-only', $commandLine);
    }

    public function testRequestPrependsSystemPromptToPrompt()
    {
        $client = new ModelClient(self::$binary);
        $model = new Codex('gpt-5-codex');

        $payload = ['prompt' => 'What is PHP?', 'system_prompt' => 'You are a pirate.'];
        $result = $client->request($model, $payload);
        $commandLine = $result->getObject()->getCommandLine();

        $this->assertStringContainsString('[System]', $commandLine);
        $this->assertStringContainsString('You are a pirate.', $commandLine);
        $this->assertStringContainsString('[User]', $commandLine);
        $this->assertStringContainsString('What is PHP?', $commandLine);
        // system_prompt should not be passed as a flag
        $this->assertStringNotContainsString('--system-prompt', $commandLine);
    }
}
