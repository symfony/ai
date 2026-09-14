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
use Symfony\AI\Mate\Command\SkillsInstallCommand;
use Symfony\AI\Mate\Skill\SkillStateRepository;
use Symfony\AI\Mate\Tests\Skill\SkillFixtureTrait;
use Symfony\AI\Mate\Tests\Skill\SkillServicesTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SkillsInstallCommandTest extends TestCase
{
    use SkillFixtureTrait;
    use SkillServicesTrait;

    private string $rootDir;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir().'/mate-skills-install-cmd-'.uniqid();
        mkdir($this->rootDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->rootDir);
    }

    public function testInstallsDeclaredSkills()
    {
        $this->createPackageWithSkill();

        $tester = new CommandTester($this->command());
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertDirectoryExists($this->rootDir.'/.agents/skills/mate-system-information');
        $this->assertFileDoesNotExist($this->rootDir.'/mate/skills.lock.php');

        $config = (new SkillStateRepository($this->rootDir))->read();
        $this->assertSame('managed', $config['vendor/pkg-a']['skills']['system-information']['state']);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Installed 1 new skill', $output);
        $this->assertStringContainsString('mate-system-information', $output);
    }

    public function testInstallsDeclaredSkillsRendersPerSkillTableWithInstalledAction()
    {
        $this->createPackageWithSkill();

        $tester = new CommandTester($this->command());
        $tester->execute([]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Installed Name', $output);
        $this->assertStringContainsString('Original', $output);
        $this->assertStringContainsString('Package', $output);
        $this->assertStringContainsString('Action', $output);
        $this->assertStringContainsString('mate-system-information', $output);
        $this->assertStringContainsString('vendor/pkg-a', $output);
        $this->assertStringContainsString('installed', $output);
    }

    public function testSecondRunIsIdempotent()
    {
        $this->createPackageWithSkill();

        (new CommandTester($this->command()))->execute([]);

        $tester = new CommandTester($this->command());
        $tester->execute([]);

        $output = $tester->getDisplay();
        $this->assertStringNotContainsString('Installed 1 new skill', $output);
        $this->assertStringContainsString('1 skill installed', $output);
    }

    public function testSecondRunShowsUnchangedActionInTable()
    {
        $this->createPackageWithSkill();

        (new CommandTester($this->command()))->execute([]);

        $tester = new CommandTester($this->command());
        $tester->execute([]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('mate-system-information', $output);
        $this->assertStringContainsString('unchanged', $output);
    }

    public function testUpdatedSourceShowsRebuiltActionInTable()
    {
        $this->createPackageWithSkill();
        (new CommandTester($this->command()))->execute([]);

        $this->createSkill($this->rootDir.'/vendor/vendor/pkg-a/skills', 'system-information', 'Inspect the runtime environment when diagnosing a version-specific problem.', 'UPDATED UPSTREAM');

        $tester = new CommandTester($this->command());
        $tester->execute([]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Rebuilt 1 skill', $output);
        $this->assertStringContainsString('mate-system-information', $output);
        $this->assertStringContainsString('rebuilt', $output);
    }

    public function testSkippedSkillAppearsInTableWithSkippedAction()
    {
        $this->createPackageWithSkill();
        (new SkillStateRepository($this->rootDir))->setMode('vendor/pkg-a', 'system-information', 'override');

        $tester = new CommandTester($this->command());
        $tester->execute([]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Skipped mate-system-information', $output);
        $this->assertStringContainsString('skipped', $output);
    }

    public function testRemovedSkillDoesNotAppearInPerSkillTable()
    {
        $this->createPackageWithSkill();
        (new CommandTester($this->command()))->execute([]);

        (new SkillStateRepository($this->rootDir))->setEnabled('vendor/pkg-a', 'system-information', false);

        $tester = new CommandTester($this->command());
        $tester->execute([]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Removed 1 skill', $output);
        $this->assertStringNotContainsString('Installed Name', $output);
    }

    public function testJsonFormatIncludesSkillsAndSummary()
    {
        $this->createPackageWithSkill();

        $tester = new CommandTester($this->command());
        $tester->execute(['--format' => 'json']);

        $decoded = json_decode($tester->getDisplay(), true);
        $this->assertIsArray($decoded);
        $this->assertFalse($decoded['dry_run']);
        $this->assertSame(['mate-system-information'], $decoded['installed']);
        $this->assertSame(1, $decoded['summary']['total']);
        $this->assertSame(1, $decoded['summary']['installed']);
        $this->assertSame('mate-system-information', $decoded['skills'][0]['installed_name']);
        $this->assertSame('installed', $decoded['skills'][0]['action']);
    }

    public function testDryRunReportsTheNewSkillWithoutWritingAnything()
    {
        $this->createPackageWithSkill();

        $tester = new CommandTester($this->command());
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertDirectoryDoesNotExist($this->rootDir.'/.agents/skills/mate-system-information');
        $this->assertFileDoesNotExist((new SkillStateRepository($this->rootDir))->path());

        $output = $tester->getDisplay();
        $this->assertStringContainsString('dry run', $output);
        $this->assertStringContainsString('Would install 1 new skill', $output);
        $this->assertStringContainsString('mate-system-information', $output);
        $this->assertStringContainsString('would install', $output);
    }

    public function testDryRunReportsAChangedSourceAsARebuild()
    {
        $this->createPackageWithSkill();
        (new CommandTester($this->command()))->execute([]);

        $this->createSkill($this->rootDir.'/vendor/vendor/pkg-a/skills', 'system-information', 'Inspect the runtime environment when diagnosing a version-specific problem.', 'UPDATED UPSTREAM');

        $tester = new CommandTester($this->command());
        $tester->execute(['--dry-run' => true]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Would rebuild 1 skill', $output);
        $this->assertStringContainsString('would rebuild', $output);
        $this->assertStringNotContainsString('UPDATED UPSTREAM', file_get_contents($this->rootDir.'/.agents/skills/mate-system-information/SKILL.md') ?: '');
    }

    public function testDryRunOnAnUpToDateProjectReportsNothingToDo()
    {
        $this->createPackageWithSkill();
        (new CommandTester($this->command()))->execute([]);

        $tester = new CommandTester($this->command());
        $tester->execute(['--dry-run' => true]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Nothing to do', $output);
        $this->assertStringNotContainsString('Would install', $output);
    }

    private function createPackageWithSkill(): void
    {
        $this->createInstalledPackage($this->rootDir);
        $this->createSkill($this->rootDir.'/vendor/vendor/pkg-a/skills', 'system-information', 'Inspect the runtime environment when diagnosing a version-specific problem.');
    }

    private function command(): SkillsInstallCommand
    {
        return new SkillsInstallCommand($this->createManager($this->rootDir));
    }
}
