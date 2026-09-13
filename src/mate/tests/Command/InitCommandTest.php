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

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\Mate\Agent\AgentInstructionsAggregator;
use Symfony\AI\Mate\Agent\AgentInstructionsMaterializer;
use Symfony\AI\Mate\Command\InitCommand;
use Symfony\AI\Mate\Runtime\InvocationPhpVersionProbe;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class InitCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/mate-test-'.uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testCreatesDirectoryAndConfigFile()
    {
        $command = $this->createCommand();
        $tester = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertDirectoryExists($this->tempDir.'/mate');
        $this->assertDirectoryExists($this->tempDir.'/mate/src');
        $this->assertFileExists($this->tempDir.'/mate/extensions.php');
        $this->assertFileExists($this->tempDir.'/mate/config.php');
        $this->assertFileExists($this->tempDir.'/mate/.env');
        $this->assertFileExists($this->tempDir.'/mate/AGENT_INSTRUCTIONS.md');
        $this->assertFileExists($this->tempDir.'/AGENTS.md');

        $content = file_get_contents($this->tempDir.'/mate/extensions.php');
        $this->assertIsString($content);
        $this->assertStringContainsString('mate discover', $content);
        $this->assertStringContainsString('enabled', $content);
    }

    public function testDoesNotGenerateMcpArtifacts()
    {
        $command = $this->createCommand();
        $tester = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertFileDoesNotExist($this->tempDir.'/mcp.json');
        $this->assertFileDoesNotExist($this->tempDir.'/.mcp.json');
        $this->assertFileDoesNotExist($this->tempDir.'/bin/codex');
        $this->assertFileDoesNotExist($this->tempDir.'/bin/codex.bat');
    }

    public function testDisplaysSuccessMessage()
    {
        $command = $this->createCommand();
        $tester = new CommandTester($command);

        $tester->execute([]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('AI Mate Initialization', $output);
        $this->assertStringContainsString('extensions.php', $output);
        $this->assertStringContainsString('config.php', $output);
        $this->assertStringContainsString('composer dump-autoload', $output);
        $this->assertStringContainsString('tools:call', $output);
        $this->assertStringContainsString('Summary', $output);
        $this->assertStringContainsString('Created', $output);
    }

    public function testDoesNotOverwriteExistingFileWithoutConfirmation()
    {
        $command = $this->createCommand();
        $tester = new CommandTester($command);

        // Create existing file
        mkdir($this->tempDir.'/mate', 0755, true);
        file_put_contents($this->tempDir.'/mate/extensions.php', '<?php return ["test" => "value"];');

        // Decline overwriting the existing extensions.php.
        // The first prompt is the agent invocation; the second is the overwrite confirmation.
        $tester->setInputs(['vendor/bin/mate', 'no']);
        $tester->execute([]);

        // File should still contain original content
        $content = file_get_contents($this->tempDir.'/mate/extensions.php');
        $this->assertIsString($content);
        $this->assertStringContainsString('test', $content);
        $this->assertStringContainsString('value', $content);
    }

    public function testOverwritesExistingFileWithConfirmation()
    {
        $command = $this->createCommand();
        $tester = new CommandTester($command);

        // Create existing file
        mkdir($this->tempDir.'/mate', 0755, true);
        file_put_contents($this->tempDir.'/mate/extensions.php', '<?php return ["test" => "value"];');

        // Confirm overwriting the existing extensions.php.
        // The first prompt is the agent invocation; the second is the overwrite confirmation.
        $tester->setInputs(['vendor/bin/mate', 'yes']);
        $tester->execute([]);

        // File should be overwritten with template content
        $content = file_get_contents($this->tempDir.'/mate/extensions.php');
        $this->assertIsString($content);
        $this->assertStringNotContainsString('test', $content);
        $this->assertStringContainsString('mate discover', $content);
        $this->assertStringContainsString('enabled', $content);
    }

    public function testRecordsTheAgentInvocationAndRuntimeInConfig()
    {
        $command = $this->createCommand();
        $tester = new CommandTester($command);

        $tester->setInputs(['ddev exec vendor/bin/mate']);
        $tester->execute([]);

        $config = file_get_contents($this->tempDir.'/mate/config.php');
        $this->assertIsString($config);
        $this->assertStringNotContainsString('##MATE_INVOCATION##', $config);
        $this->assertStringNotContainsString('##MATE_PHP_VERSION##', $config);
        $this->assertStringContainsString("'ddev exec vendor/bin/mate'", $config);
        $this->assertStringContainsString(
            "'".\PHP_MAJOR_VERSION.'.'.\PHP_MINOR_VERSION."'",
            $config,
        );
    }

    public function testDoesNotProbeWhenTheInvocationIsTheBareBinary()
    {
        $probe = new InvocationPhpVersionProbe(null, function (array $command): string {
            $this->fail('The bare binary runs on the host directly; it must not be probed.');
        });

        $command = $this->createCommand($probe);
        $tester = new CommandTester($command);

        $tester->setInputs(['vendor/bin/mate']);
        $tester->execute([]);

        $config = file_get_contents($this->tempDir.'/mate/config.php');
        $this->assertIsString($config);
        $this->assertStringContainsString(
            "'".\PHP_MAJOR_VERSION.'.'.\PHP_MINOR_VERSION."'",
            $config,
        );
    }

    /**
     * A wrapped invocation runs under whatever PHP the wrapper routes to, which may differ
     * from the host process running `mate init`; a working probe must pin that real version.
     */
    public function testWrappedInvocationWithAWorkingProbeUsesTheDetectedPhpVersion()
    {
        $probe = new InvocationPhpVersionProbe(null, static function (array $command): string {
            return 'MATE_PHP_VERSION=7.4';
        });

        $command = $this->createCommand($probe);
        $tester = new CommandTester($command);

        $tester->setInputs(['ddev exec vendor/bin/mate']);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $config = file_get_contents($this->tempDir.'/mate/config.php');
        $this->assertIsString($config);
        $this->assertStringContainsString("'7.4'", $config);
        $this->assertStringNotContainsString(\PHP_MAJOR_VERSION.'.'.\PHP_MINOR_VERSION, $config);
        $this->assertStringNotContainsString('Could not determine', $tester->getDisplay());
    }

    /**
     * A failed probe must not crash `init` or block file generation: it falls back to the
     * current process's version, exactly like today, and warns so the developer can fix
     * "mate.php_version" in mate/config.php by hand if that guess is wrong.
     */
    public function testWrappedInvocationWithAFailingProbeFallsBackAndWarns()
    {
        $probe = new InvocationPhpVersionProbe(null, static function (array $command): ?string {
            return null;
        });

        $command = $this->createCommand($probe);
        $tester = new CommandTester($command);

        $tester->setInputs(['ddev exec vendor/bin/mate']);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $config = file_get_contents($this->tempDir.'/mate/config.php');
        $this->assertIsString($config);
        $this->assertStringContainsString(
            "'".\PHP_MAJOR_VERSION.'.'.\PHP_MINOR_VERSION."'",
            $config,
        );

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Could not determine', $display);
        $this->assertStringContainsString('ddev exec vendor/bin/mate', $display);
    }

    /**
     * The managed AGENTS.md block promises one command; the file it points at must not name
     * another one until `discover` happens to run.
     */
    public function testWritesTheAgentInvocationIntoTheGeneratedInstructions()
    {
        $command = $this->createCommand();
        $tester = new CommandTester($command);

        $tester->setInputs(['ddev exec vendor/bin/mate']);
        $tester->execute([]);

        $instructions = file_get_contents($this->tempDir.'/mate/AGENT_INSTRUCTIONS.md');
        $this->assertIsString($instructions);
        $this->assertStringNotContainsString('##MATE_INVOCATION##', $instructions);
        $this->assertStringContainsString('ddev exec vendor/bin/mate tools:list', $instructions);

        $agents = file_get_contents($this->tempDir.'/AGENTS.md');
        $this->assertIsString($agents);
        $this->assertStringContainsString('ddev exec vendor/bin/mate', $agents);
    }

    /**
     * A wrapper on its own is the natural answer to "which command", and materializing it
     * verbatim would produce "symfony php tools:list", which runs nothing.
     */
    public function testCompletesABareWrapperWithTheBinary()
    {
        $command = $this->createCommand();
        $tester = new CommandTester($command);

        $tester->setInputs(['symfony php']);
        $tester->execute([]);

        $config = file_get_contents($this->tempDir.'/mate/config.php');
        $this->assertIsString($config);
        $this->assertStringContainsString("'symfony php vendor/bin/mate'", $config);

        $instructions = file_get_contents($this->tempDir.'/mate/AGENT_INSTRUCTIONS.md');
        $this->assertIsString($instructions);
        $this->assertStringContainsString('symfony php vendor/bin/mate tools:list', $instructions);
    }

    public function testKeepsAnInvocationThatAlreadyNamesTheBinary()
    {
        $command = $this->createCommand();
        $tester = new CommandTester($command);

        $tester->setInputs(['docker compose exec app php bin/mate']);
        $tester->execute([]);

        $config = file_get_contents($this->tempDir.'/mate/config.php');
        $this->assertIsString($config);
        $this->assertStringContainsString("'docker compose exec app php bin/mate'", $config);
    }

    /**
     * Re-running `init` after an upgrade is the natural thing to try, and the only safe answer to
     * the overwrite prompt is "no", because the template would discard the services the user
     * registered. AGENTS.md is the file the agent reads, so it has to keep naming the command the
     * kept config.php configures rather than the answer that went nowhere.
     */
    public function testKeepingTheConfigKeepsItsInvocationInTheInstructions()
    {
        $this->scaffoldInitializedProject('ddev exec vendor/bin/mate');

        $command = $this->createCommand(null, 'ddev exec vendor/bin/mate');
        $tester = new CommandTester($command);

        // One "no" per scaffolded file. No invocation prompt: the config stays as it is.
        $tester->setInputs(['no', 'no', 'no', 'no', 'no']);
        $tester->execute([]);

        $agents = file_get_contents($this->tempDir.'/AGENTS.md');
        $this->assertIsString($agents);
        $this->assertStringContainsString('ddev exec vendor/bin/mate', $agents);

        $config = file_get_contents($this->tempDir.'/mate/config.php');
        $this->assertIsString($config);
        $this->assertStringContainsString('a service the user registered themselves', $config);
    }

    /**
     * `mate.php_version` is nullable on purpose: null turns the interpreter check off. Passing the
     * PHP that happens to run `init` in its place would make the generated instructions promise a
     * refusal nothing enforces, which is precisely what an upgraded project without the parameter
     * must not be told.
     */
    public function testAProjectWithoutAPinnedVersionIsNotPromisedTheInterpreterCheck()
    {
        $this->scaffoldInitializedProject('vendor/bin/mate');

        $command = $this->createCommand(null, 'vendor/bin/mate', null);
        $tester = new CommandTester($command);

        $tester->setInputs(['no', 'no', 'no', 'no', 'no']);
        $tester->execute([]);

        $agents = file_get_contents($this->tempDir.'/AGENTS.md');
        $this->assertIsString($agents);
        $this->assertStringNotContainsString('refuses to start', $agents);
    }

    public function testAProjectWithAPinnedVersionIsPromisedTheInterpreterCheck()
    {
        $this->scaffoldInitializedProject('vendor/bin/mate');

        $command = $this->createCommand(null, 'vendor/bin/mate', '8.3');
        $tester = new CommandTester($command);

        $tester->setInputs(['no', 'no', 'no', 'no', 'no']);
        $tester->execute([]);

        $agents = file_get_contents($this->tempDir.'/AGENTS.md');
        $this->assertIsString($agents);
        $this->assertStringContainsString('refuses to start', $agents);
    }

    public function testDoesNotAskForAnInvocationItWouldDiscard()
    {
        $this->scaffoldInitializedProject('ddev exec vendor/bin/mate');

        $command = $this->createCommand(null, 'ddev exec vendor/bin/mate');
        $tester = new CommandTester($command);

        $tester->setInputs(['no', 'no', 'no', 'no', 'no']);
        $tester->execute([]);

        $this->assertStringNotContainsString('Which command should your coding agent use', $tester->getDisplay());
    }

    public function testReplacingTheConfigAsksForTheInvocationAgain()
    {
        $this->scaffoldInitializedProject('ddev exec vendor/bin/mate');

        $command = $this->createCommand(null, 'ddev exec vendor/bin/mate');
        $tester = new CommandTester($command);

        // extensions.php: no, config.php: yes, then the invocation, then the remaining files.
        $tester->setInputs(['no', 'yes', 'symfony php', 'no', 'no', 'no']);
        $tester->execute([]);

        $config = file_get_contents($this->tempDir.'/mate/config.php');
        $this->assertIsString($config);
        $this->assertStringContainsString("'symfony php vendor/bin/mate'", $config);

        $agents = file_get_contents($this->tempDir.'/AGENTS.md');
        $this->assertIsString($agents);
        $this->assertStringContainsString('symfony php vendor/bin/mate', $agents);
    }

    /**
     * The instructions carry the invocation too, so replacing them while keeping the config must
     * not fall back to the template default either.
     */
    public function testReplacingOnlyTheInstructionsKeepsTheConfiguredInvocation()
    {
        $this->scaffoldInitializedProject('ddev exec vendor/bin/mate');

        $command = $this->createCommand(null, 'ddev exec vendor/bin/mate');
        $tester = new CommandTester($command);

        // Everything declined except AGENT_INSTRUCTIONS.md, the last of the five.
        $tester->setInputs(['no', 'no', 'no', 'no', 'yes']);
        $tester->execute([]);

        $instructions = file_get_contents($this->tempDir.'/mate/AGENT_INSTRUCTIONS.md');
        $this->assertIsString($instructions);
        $this->assertStringNotContainsString('##MATE_INVOCATION##', $instructions);
        $this->assertStringContainsString('ddev exec vendor/bin/mate tools:list', $instructions);
    }

    public function testDefaultsTheInvocationToThePlainBinary()
    {
        $command = $this->createCommand();
        $tester = new CommandTester($command);

        $tester->execute([]);

        $config = file_get_contents($this->tempDir.'/mate/config.php');
        $this->assertIsString($config);
        $this->assertStringContainsString("'vendor/bin/mate'", $config);
    }

    public function testCreatesDirectoryIfNotExists()
    {
        $command = $this->createCommand();
        $tester = new CommandTester($command);

        // Ensure mate directory doesn't exist
        $this->assertDirectoryDoesNotExist($this->tempDir.'/mate');

        $tester->execute([]);

        // Directory should be created
        $this->assertDirectoryExists($this->tempDir.'/mate');
        $this->assertFileExists($this->tempDir.'/mate/extensions.php');
        $this->assertFileExists($this->tempDir.'/mate/config.php');
    }

    public function testSetsExtensionFalseByDefault()
    {
        // Create composer.json without ai-mate config
        file_put_contents($this->tempDir.'/composer.json', json_encode(['name' => 'test/package']));

        $command = $this->createCommand();
        $tester = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        // Verify composer.json has extension: false by default
        $composerContent = file_get_contents($this->tempDir.'/composer.json');
        $this->assertIsString($composerContent);
        $composerJson = json_decode($composerContent, true);
        $this->assertIsArray($composerJson);
        $this->assertArrayHasKey('extra', $composerJson);
        $this->assertArrayHasKey('ai-mate', $composerJson['extra']);
        $this->assertArrayHasKey('extension', $composerJson['extra']['ai-mate']);
        $this->assertFalse($composerJson['extra']['ai-mate']['extension']);
    }

    public function testPreservesNonAsciiCharactersInComposerJson()
    {
        file_put_contents($this->tempDir.'/composer.json', json_encode([
            'name' => 'test/package',
            'authors' => [['name' => 'Michał Pysiak']],
        ], \JSON_UNESCAPED_UNICODE));

        $command = $this->createCommand();
        $tester = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $composerContent = file_get_contents($this->tempDir.'/composer.json');
        $this->assertIsString($composerContent);
        $this->assertStringContainsString('Michał Pysiak', $composerContent);
        $this->assertStringNotContainsString('\\u', $composerContent);
    }

    public function testScaffoldsSensitiveFilesWithSecurePermissions()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Permission-based tests are not reliable on Windows');
        }

        $command = $this->createCommand();
        $tester = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame(0750, fileperms($this->tempDir.'/mate') & 0777);
        $this->assertSame(0640, fileperms($this->tempDir.'/mate/.env') & 0777);
        $this->assertSame(0640, fileperms($this->tempDir.'/mate/config.php') & 0777);
    }

    /**
     * The default probe is inert on purpose. A real one would run the wrapper from the test's
     * inputs, so the suite would shell out to whatever `ddev`, `symfony` or `docker` happens to be
     * installed, in the developer's current directory. Tests that care about probing pass their
     * own runner.
     *
     * `$invocation` and `$pinnedPhpVersion` stand for what the container loaded from an existing
     * `mate/config.php`, which is what a re-run in an initialized project sees.
     */
    private function createCommand(
        ?InvocationPhpVersionProbe $phpVersionProbe = null,
        string $invocation = 'vendor/bin/mate',
        ?string $pinnedPhpVersion = null,
    ): InitCommand {
        $logger = new NullLogger();
        $aggregator = new AgentInstructionsAggregator($this->tempDir, [], $logger);
        $materializer = new AgentInstructionsMaterializer($this->tempDir, $aggregator, $logger, $invocation, $pinnedPhpVersion);

        $probe = $phpVersionProbe ?? new InvocationPhpVersionProbe(null, static fn (array $command): ?string => null);

        return new InitCommand($this->tempDir, $materializer, $probe, $invocation, $pinnedPhpVersion);
    }

    /**
     * Writes the scaffolded files a re-run finds, with the invocation the project configured.
     */
    private function scaffoldInitializedProject(string $invocation): void
    {
        mkdir($this->tempDir.'/mate', 0755, true);
        file_put_contents($this->tempDir.'/mate/config.php', "<?php\n\n// \$container->parameters()->set('mate.invocation', '".$invocation."');\n// a service the user registered themselves\n");
        file_put_contents($this->tempDir.'/mate/extensions.php', '<?php return [];');
        file_put_contents($this->tempDir.'/mate/.env', '');
        file_put_contents($this->tempDir.'/mate/.gitignore', '');
        file_put_contents($this->tempDir.'/mate/AGENT_INSTRUCTIONS.md', 'Run `'.$invocation.' tools:list`.');
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            if (is_link($path)) {
                unlink($path);
            } elseif (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
