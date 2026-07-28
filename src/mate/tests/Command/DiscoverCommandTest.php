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
use Symfony\AI\Mate\Command\DiscoverCommand;
use Symfony\AI\Mate\Discovery\ComposerExtensionDiscovery;
use Symfony\AI\Mate\Service\ExtensionConfigSynchronizer;
use Symfony\AI\Mate\Skill\Linker;
use Symfony\AI\Mate\Skill\SkillContentHasher;
use Symfony\AI\Mate\Skill\SkillDiscovery;
use Symfony\AI\Mate\Skill\SkillFrontmatter;
use Symfony\AI\Mate\Skill\SkillInstaller;
use Symfony\AI\Mate\Skill\SkillStateRepository;
use Symfony\AI\Mate\Tests\Skill\SkillFixtureTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class DiscoverCommandTest extends TestCase
{
    // Brings in the link-aware removeDirectory(): the generated .claude/ mirror is a symlink,
    // so teardown must not recurse through it into the .agents/ copy.
    use SkillFixtureTrait;

    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = __DIR__.'/../Discovery/Fixtures';
    }

    public function testDiscoversExtensionsAndCreatesFile()
    {
        $tempDir = sys_get_temp_dir().'/mate-discover-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        try {
            $rootDir = $this->createConfiguration($this->fixturesDir.'/with-ai-mate-config', $tempDir);
            $command = $this->createCommand($rootDir);
            $tester = new CommandTester($command);

            $tester->execute([]);

            $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
            $this->assertFileExists($tempDir.'/mate/extensions.php');

            $extensions = include $tempDir.'/mate/extensions.php';
            $this->assertIsArray($extensions);
            $this->assertArrayHasKey('vendor/package-a', $extensions);
            $this->assertArrayHasKey('vendor/package-b', $extensions);
            $this->assertIsArray($extensions['vendor/package-a']);
            $this->assertIsArray($extensions['vendor/package-b']);
            $this->assertTrue($extensions['vendor/package-a']['enabled']);
            $this->assertTrue($extensions['vendor/package-b']['enabled']);
            $this->assertFileExists($tempDir.'/mate/AGENT_INSTRUCTIONS.md');
            $this->assertFileExists($tempDir.'/AGENTS.md');

            $agentsContent = file_get_contents($tempDir.'/AGENTS.md');
            $this->assertIsString($agentsContent);
            $this->assertStringContainsString(AgentInstructionsMaterializer::AGENTS_START_MARKER, $agentsContent);
            $this->assertStringContainsString(AgentInstructionsMaterializer::AGENTS_END_MARKER, $agentsContent);

            $output = $tester->getDisplay();
            $this->assertStringContainsString('Discovered 2 Extension', $output);
            $this->assertStringContainsString('vendor/package-a', $output);
            $this->assertStringContainsString('vendor/package-b', $output);
            $this->assertStringContainsString('Updated mate/AGENT_INSTRUCTIONS.md', $output);
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    public function testPreservesExistingEnabledState()
    {
        $tempDir = sys_get_temp_dir().'/mate-discover-test-'.uniqid();
        mkdir($tempDir.'/mate', 0755, true);

        try {
            // Create existing extensions.php with package-a disabled
            file_put_contents($tempDir.'/mate/extensions.php', <<<'PHP'
<?php
return [
    'vendor/package-a' => ['enabled' => false],
    'vendor/package-b' => ['enabled' => true],
];
PHP
            );

            $rootDir = $this->createConfiguration($this->fixturesDir.'/with-ai-mate-config', $tempDir);
            $command = $this->createCommand($rootDir);
            $tester = new CommandTester($command);

            $tester->execute([]);

            $extensions = include $tempDir.'/mate/extensions.php';
            $this->assertIsArray($extensions);
            $this->assertIsArray($extensions['vendor/package-a']);
            $this->assertIsArray($extensions['vendor/package-b']);
            $this->assertFalse($extensions['vendor/package-a']['enabled'], 'Should preserve disabled state');
            $this->assertTrue($extensions['vendor/package-b']['enabled'], 'Should preserve enabled state');
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    public function testNewPackagesDefaultToEnabled()
    {
        $tempDir = sys_get_temp_dir().'/mate-discover-test-'.uniqid();
        mkdir($tempDir.'/mate', 0755, true);

        try {
            // Create existing extensions.php with only package-a
            file_put_contents($tempDir.'/mate/extensions.php', <<<'PHP'
<?php
return [
    'vendor/package-a' => ['enabled' => false],
];
PHP
            );

            $rootDir = $this->createConfiguration($this->fixturesDir.'/with-ai-mate-config', $tempDir);
            $command = $this->createCommand($rootDir);
            $tester = new CommandTester($command);

            $tester->execute([]);

            $extensions = include $tempDir.'/mate/extensions.php';
            $this->assertIsArray($extensions);
            $this->assertIsArray($extensions['vendor/package-a']);
            $this->assertIsArray($extensions['vendor/package-b']);
            $this->assertFalse($extensions['vendor/package-a']['enabled'], 'Existing disabled state preserved');
            $this->assertTrue($extensions['vendor/package-b']['enabled'], 'New package defaults to enabled');
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    public function testIgnoreMissingFileExitsEarlyWhenExtensionsFileAbsent()
    {
        $tempDir = sys_get_temp_dir().'/mate-discover-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        try {
            $rootDir = $this->createConfiguration($this->fixturesDir.'/with-ai-mate-config', $tempDir);
            $command = $this->createCommand($rootDir);
            $tester = new CommandTester($command);

            $tester->execute(['--ignore-missing-file' => true]);

            $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
            $this->assertFileDoesNotExist($tempDir.'/mate/extensions.php');
            $this->assertFileDoesNotExist($tempDir.'/mate/AGENT_INSTRUCTIONS.md');
            $this->assertSame('', $tester->getDisplay());
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    public function testIgnoreMissingFileRunsNormallyWhenExtensionsFileExists()
    {
        $tempDir = sys_get_temp_dir().'/mate-discover-test-'.uniqid();
        mkdir($tempDir.'/mate', 0755, true);

        try {
            file_put_contents($tempDir.'/mate/extensions.php', <<<'PHP'
<?php
return [];
PHP
            );

            $rootDir = $this->createConfiguration($this->fixturesDir.'/with-ai-mate-config', $tempDir);
            $command = $this->createCommand($rootDir);
            $tester = new CommandTester($command);

            $tester->execute(['--ignore-missing-file' => true]);

            $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
            $extensions = include $tempDir.'/mate/extensions.php';
            $this->assertIsArray($extensions);
            $this->assertArrayHasKey('vendor/package-a', $extensions);
            $this->assertArrayHasKey('vendor/package-b', $extensions);
            $this->assertFileExists($tempDir.'/mate/AGENT_INSTRUCTIONS.md');
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    public function testDisplaysWarningWhenNoExtensionsFound()
    {
        $tempDir = sys_get_temp_dir().'/mate-discover-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        try {
            $rootDir = $this->createConfiguration($this->fixturesDir.'/without-ai-mate-config', $tempDir);
            $command = $this->createCommand($rootDir);
            $tester = new CommandTester($command);

            $tester->execute([]);

            $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

            $output = $tester->getDisplay();
            $this->assertStringContainsString('No MCP extensions found', $output);
            $this->assertFileExists($tempDir.'/mate/AGENT_INSTRUCTIONS.md');
            $this->assertFileExists($tempDir.'/AGENTS.md');
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    public function testInstallsSkillsAndRecordsState()
    {
        $tempDir = sys_get_temp_dir().'/mate-discover-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        try {
            $rootDir = $this->createConfiguration($this->fixturesDir.'/with-skills', $tempDir);
            $command = $this->createCommand($rootDir);
            $tester = new CommandTester($command);

            $tester->execute([]);

            $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

            $extensions = include $tempDir.'/mate/extensions.php';
            $this->assertIsArray($extensions);
            $this->assertArrayHasKey('vendor/package-with-skills', $extensions);
            // Hashes are content-derived, so assert the stable keys individually.
            $state = $extensions['vendor/package-with-skills']['skills']['demo-skill'];
            $this->assertTrue($state['enabled']);
            $this->assertSame('managed', $state['mode']);
            $this->assertSame('managed', $state['state']);
            $this->assertSame('vendor/vendor/package-with-skills/skills/demo-skill', $state['source']);
            $this->assertStringStartsWith('sha256:', $state['source_hash']);
            $this->assertStringStartsWith('sha256:', $state['hash']);
            $this->assertSame(
                ['.agents/skills/mate-demo-skill', '.claude/skills/mate-demo-skill'],
                $state['targets'],
            );

            $this->assertDirectoryExists($tempDir.'/.agents/skills/mate-demo-skill');
            $this->assertFileDoesNotExist($tempDir.'/mate/skills.lock.php');

            $generated = file_get_contents($tempDir.'/.agents/skills/mate-demo-skill/SKILL.md');
            $this->assertIsString($generated);
            $this->assertStringContainsString('name: mate-demo-skill', $generated);

            $this->assertStringContainsString('mate-demo-skill', $tester->getDisplay());
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    public function testPrunesSkillsWhenTheLastExtensionDisappears()
    {
        $tempDir = sys_get_temp_dir().'/mate-discover-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        try {
            $rootDir = $this->createConfiguration($this->fixturesDir.'/with-skills', $tempDir);
            (new CommandTester($this->createCommand($rootDir)))->execute([]);
            $this->assertDirectoryExists($tempDir.'/.agents/skills/mate-demo-skill');

            // The package is gone, so discovery yields neither extensions nor skills. The generated
            // folders must still be cleaned up instead of being left behind forever.
            $this->removeDirectory($tempDir.'/vendor/vendor/package-with-skills');

            (new CommandTester($this->createCommand($rootDir)))->execute([]);

            $this->assertDirectoryDoesNotExist($tempDir.'/.agents/skills/mate-demo-skill');
            $this->assertFalse(is_link($tempDir.'/.claude/skills/mate-demo-skill'));

            $extensions = include $tempDir.'/mate/extensions.php';
            $this->assertIsArray($extensions);
            $this->assertArrayNotHasKey('skills', $extensions['vendor/package-with-skills'] ?? []);
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    private function createConfiguration(string $rootDir, string $tempDir): string
    {
        // Copy fixture to temp directory for testing
        $this->copyDirectory($rootDir.'/vendor', $tempDir.'/vendor');
        if (file_exists($rootDir.'/composer.json')) {
            copy($rootDir.'/composer.json', $tempDir.'/composer.json');
        }

        return $tempDir;
    }

    private function copyDirectory(string $src, string $dst): void
    {
        if (!is_dir($src)) {
            return;
        }

        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }

        $files = array_diff(scandir($src) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $srcPath = $src.'/'.$file;
            $dstPath = $dst.'/'.$file;

            if (is_dir($srcPath)) {
                $this->copyDirectory($srcPath, $dstPath);
            } else {
                copy($srcPath, $dstPath);
            }
        }
    }

    private function createCommand(string $rootDir): DiscoverCommand
    {
        $logger = new NullLogger();
        $frontmatter = new SkillFrontmatter();
        $repository = new SkillStateRepository($rootDir);

        return new DiscoverCommand(
            new ComposerExtensionDiscovery($rootDir, $logger),
            new ExtensionConfigSynchronizer($repository),
            new AgentInstructionsMaterializer($rootDir, new AgentInstructionsAggregator($rootDir, [], $logger), $logger),
            new SkillDiscovery($rootDir, $frontmatter, $logger),
            new SkillInstaller($rootDir, $repository, $frontmatter, new SkillContentHasher(), new Linker(), $logger),
        );
    }
}
