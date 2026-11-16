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
use Symfony\AI\Platform\Bridge\Codex\Contract\CodexContract;
use Symfony\AI\Platform\Bridge\Codex\Exception\CliNotFoundException;
use Symfony\AI\Platform\Bridge\Codex\ModelClient;
use Symfony\AI\Platform\Bridge\Codex\RawProcessResult;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\MissingModelSupportException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

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

    public function testBuildCommandWithAllowedToolsThrowsException()
    {
        $client = new ModelClient(self::$binary);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "tools" and "allowed_tools" options are not supported, "codex exec" has no option to restrict its tools.');

        $client->buildCommand('Hello', ['allowed_tools' => ['Bash', 'Read']]);
    }

    public function testBuildCommandWithAllOptions()
    {
        $client = new ModelClient(self::$binary);

        $command = $client->buildCommand('Hello', [
            'model' => 'gpt-5-codex',
            'sandbox' => 'workspace-write',
        ]);

        $expected = [
            self::$binary,
            'exec', '--json',
            '--model', 'gpt-5-codex',
            '--sandbox', 'workspace-write',
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

    public function testBuildCommandWithToolNamesThrowsException()
    {
        $client = new ModelClient(self::$binary);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "tools" and "allowed_tools" options are not supported, "codex exec" has no option to restrict its tools.');

        $client->buildCommand('Hello', ['tools' => ['Bash', 'Read']]);
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

    public function testRequestWithToolDefinitionsThrowsException()
    {
        $client = new ModelClient(self::$binary);
        $model = new Codex('gpt-5-codex');

        $this->expectException(MissingModelSupportException::class);
        $this->expectExceptionMessage('Model "gpt-5-codex" (Symfony\AI\Platform\Bridge\Codex\Codex) does not support "tool calling".');

        $client->request($model, 'Hello', ['tools' => self::toolDefinitions($model)]);
    }

    public function testRequestWithToolNamesAndDefinitionsThrowsMissingModelSupportException()
    {
        $client = new ModelClient(self::$binary);
        $model = new Codex('gpt-5-codex');

        $this->expectException(MissingModelSupportException::class);

        $client->request($model, 'Hello', ['tools' => ['Read', ...self::toolDefinitions($model)]]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function toolDefinitions(Codex $model): array
    {
        return CodexContract::create()->createToolOption([
            new Tool(new ExecutionReference(self::class), 'clock', 'Provides the current date and time.'),
        ], $model);
    }
}
