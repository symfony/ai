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
use Symfony\AI\Mate\Command\SkillsPruneCommand;
use Symfony\AI\Mate\Tests\Skill\SkillFixtureTrait;
use Symfony\AI\Mate\Tests\Skill\SkillServicesTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SkillsPruneCommandTest extends TestCase
{
    use SkillFixtureTrait;
    use SkillServicesTrait;

    private string $rootDir;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir().'/mate-skills-prune-cmd-'.uniqid();
        mkdir($this->rootDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->rootDir);
    }

    public function testReportsNothingToPruneOnACleanInstall()
    {
        $this->installFixture();

        $tester = new CommandTester($this->command());
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Nothing to prune', $tester->getDisplay());
    }

    public function testRemovesStrayGeneratedFolders()
    {
        $this->installFixture();
        mkdir($this->rootDir.'/.agents/skills/mate-left-over', 0777, true);
        mkdir($this->rootDir.'/.claude/skills/mate-left-over', 0777, true);

        $tester = new CommandTester($this->command());
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('mate-left-over', $tester->getDisplay());
        $this->assertDirectoryDoesNotExist($this->rootDir.'/.agents/skills/mate-left-over');
        $this->assertDirectoryDoesNotExist($this->rootDir.'/.claude/skills/mate-left-over');
        $this->assertDirectoryExists($this->rootDir.'/.agents/skills/mate-system-information');
    }

    public function testLeavesSkillsTheUserMaintainsAlone()
    {
        $this->installFixture();
        mkdir($this->rootDir.'/.agents/skills/my-own-skill', 0777, true);

        (new CommandTester($this->command()))->execute([]);

        $this->assertDirectoryExists($this->rootDir.'/.agents/skills/my-own-skill');
    }

    public function testDryRunKeepsEverythingInPlace()
    {
        $this->installFixture();
        mkdir($this->rootDir.'/.agents/skills/mate-left-over', 0777, true);

        $tester = new CommandTester($this->command());
        $tester->execute(['--dry-run' => true]);

        $this->assertStringContainsString('would be removed', $tester->getDisplay());
        $this->assertDirectoryExists($this->rootDir.'/.agents/skills/mate-left-over');
    }

    private function installFixture(): void
    {
        $this->createInstalledPackage($this->rootDir);
        $this->createSkill($this->rootDir.'/vendor/vendor/pkg-a/skills', 'system-information', 'System info.');
        $this->createManager($this->rootDir)->reinstall();
    }

    private function command(): SkillsPruneCommand
    {
        return new SkillsPruneCommand($this->createManager($this->rootDir));
    }
}
