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
use Symfony\AI\Mate\Command\SkillsListCommand;
use Symfony\AI\Mate\Skill\SkillStateRepository;
use Symfony\AI\Mate\Tests\Skill\SkillFixtureTrait;
use Symfony\AI\Mate\Tests\Skill\SkillServicesTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SkillsListCommandTest extends TestCase
{
    use SkillFixtureTrait;
    use SkillServicesTrait;

    private string $rootDir;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir().'/mate-skills-list-cmd-'.uniqid();
        mkdir($this->rootDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->rootDir);
    }

    public function testListsNotInstalledSkillBeforeInstall()
    {
        $this->createPackageWithSkill();

        $tester = new CommandTester($this->command());
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('mate-system-information', $output);
        $this->assertStringContainsString('not installed', $output);
    }

    public function testListsOkStatusAfterInstall()
    {
        $this->createPackageWithSkill();
        $this->createManager($this->rootDir)->reinstall();

        $tester = new CommandTester($this->command());
        $tester->execute([]);

        $this->assertStringContainsString('mate-system-information', $tester->getDisplay());
        $this->assertStringContainsString('ok', $tester->getDisplay());
    }

    public function testShowsModeAndStateColumns()
    {
        $this->createPackageWithSkill();
        $this->createManager($this->rootDir)->reinstall();

        $tester = new CommandTester($this->command());
        $tester->execute(['--format' => 'json']);

        $decoded = json_decode($tester->getDisplay(), true);
        $this->assertIsArray($decoded);
        $this->assertSame('managed', $decoded['skills'][0]['mode']);
        $this->assertSame('managed', $decoded['skills'][0]['state']);
    }

    public function testReportsDisabledSkill()
    {
        $this->createPackageWithSkill();
        $this->createManager($this->rootDir)->reinstall();
        (new SkillStateRepository($this->rootDir))->setEnabled('vendor/pkg-a', 'system-information', false);
        $this->createManager($this->rootDir)->reinstall();

        $tester = new CommandTester($this->command());
        $tester->execute(['--format' => 'json']);

        $decoded = json_decode($tester->getDisplay(), true);
        $this->assertIsArray($decoded);
        $this->assertFalse($decoded['skills'][0]['enabled']);
        $this->assertSame('disabled', $decoded['skills'][0]['state']);
        $this->assertSame('disabled', $decoded['skills'][0]['status']);
    }

    public function testJsonFormat()
    {
        $this->createPackageWithSkill();
        $this->createManager($this->rootDir)->reinstall();

        $tester = new CommandTester($this->command());
        $tester->execute(['--format' => 'json']);

        $decoded = json_decode($tester->getDisplay(), true);
        $this->assertIsArray($decoded);
        $this->assertSame(1, $decoded['summary']['total']);
        $this->assertSame('mate-system-information', $decoded['skills'][0]['installed_name']);
        $this->assertSame('ok', $decoded['skills'][0]['status']);
    }

    private function createPackageWithSkill(): void
    {
        $this->createInstalledPackage($this->rootDir);
        $this->createSkill($this->rootDir.'/vendor/vendor/pkg-a/skills', 'system-information', 'System info.');
    }

    private function command(): SkillsListCommand
    {
        return new SkillsListCommand($this->createManager($this->rootDir));
    }
}
