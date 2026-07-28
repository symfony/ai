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

    private function createPackageWithSkill(): void
    {
        $this->createInstalledPackage($this->rootDir);
        $this->createSkill($this->rootDir.'/vendor/vendor/pkg-a/skills', 'system-information', 'System info.');
    }

    private function command(): SkillsInstallCommand
    {
        return new SkillsInstallCommand($this->createManager($this->rootDir));
    }
}
