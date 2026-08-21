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
use Symfony\AI\Mate\Command\SkillsValidateCommand;
use Symfony\AI\Mate\Skill\SkillStateRepository;
use Symfony\AI\Mate\Tests\Skill\SkillFixtureTrait;
use Symfony\AI\Mate\Tests\Skill\SkillServicesTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SkillsValidateCommandTest extends TestCase
{
    use SkillFixtureTrait;
    use SkillServicesTrait;

    private string $rootDir;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir().'/mate-skills-validate-cmd-'.uniqid();
        mkdir($this->rootDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->rootDir);
    }

    public function testPassesOnAFreshInstall()
    {
        $this->installFixture();

        $tester = new CommandTester($this->command());
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('All 1 skill(s) are valid', $tester->getDisplay());
    }

    public function testFailsWhenGeneratedContentWasEditedByHand()
    {
        $this->installFixture();
        file_put_contents(
            $this->rootDir.'/.agents/skills/mate-system-information/SKILL.md',
            "---\nname: mate-system-information\ndescription: tampered\n---\n\nTAMPERED",
        );

        $tester = new CommandTester($this->command());
        $tester->execute([]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('modified by hand', $tester->getDisplay());
    }

    public function testFailsWhenAGeneratedFolderIsMissing()
    {
        $this->installFixture();
        $this->removeDirectory($this->rootDir.'/.agents/skills/mate-system-information');

        $tester = new CommandTester($this->command());
        $tester->execute([]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Missing generated folder', $tester->getDisplay());
    }

    public function testFailsWhenADisabledSkillStillHasAFolder()
    {
        $this->installFixture();
        (new SkillStateRepository($this->rootDir))->setEnabled('vendor/pkg-a', 'system-information', false);
        $this->createManager($this->rootDir)->reinstall();

        mkdir($this->rootDir.'/.agents/skills/mate-system-information', 0777, true);

        $tester = new CommandTester($this->command());
        $tester->execute([]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('disabled but a generated folder still exists', $tester->getDisplay());
    }

    public function testWarnsWhenTheSourceChangedSinceTheLastInstall()
    {
        $this->installFixture();
        $this->createSkill($this->rootDir.'/vendor/vendor/pkg-a/skills', 'system-information', 'System info.', 'UPDATED UPSTREAM');

        $tester = new CommandTester($this->command());
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Source changed since the last install', $tester->getDisplay());
        $this->assertStringContainsString('Validation passed with warnings', $tester->getDisplay());
    }

    public function testStrictTurnsWarningsIntoFailures()
    {
        $this->installFixture();
        $this->createSkill($this->rootDir.'/vendor/vendor/pkg-a/skills', 'system-information', 'System info.', 'UPDATED UPSTREAM');

        $tester = new CommandTester($this->command());
        $tester->execute(['--strict' => true]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    public function testReportsStrayFolders()
    {
        $this->installFixture();
        mkdir($this->rootDir.'/.agents/skills/mate-left-over', 0777, true);

        $tester = new CommandTester($this->command());
        $tester->execute([]);

        $this->assertStringContainsString('mate-left-over', $tester->getDisplay());
        $this->assertStringContainsString('skills:prune', $tester->getDisplay());
    }

    public function testFailsOnUnknownSkillName()
    {
        $this->installFixture();

        $tester = new CommandTester($this->command());
        $tester->execute(['name' => 'mate-nope']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Unknown skill', $tester->getDisplay());
    }

    public function testAcceptsBothInstalledAndOriginalNames()
    {
        $this->installFixture();

        foreach (['mate-system-information', 'system-information'] as $name) {
            $tester = new CommandTester($this->command());
            $tester->execute(['name' => $name]);

            $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        }
    }

    public function testJsonFormatReportsIssues()
    {
        $this->installFixture();
        $this->removeDirectory($this->rootDir.'/.agents/skills/mate-system-information');

        $tester = new CommandTester($this->command());
        $tester->execute(['--format' => 'json']);

        $decoded = json_decode($tester->getDisplay(), true);
        $this->assertIsArray($decoded);
        $this->assertFalse($decoded['valid']);
        $this->assertSame('mate-system-information', $decoded['skills'][0]['name']);
        $this->assertNotSame([], $decoded['skills'][0]['issues']);
    }

    private function installFixture(): void
    {
        $this->createInstalledPackage($this->rootDir);
        $this->createSkill($this->rootDir.'/vendor/vendor/pkg-a/skills', 'system-information', 'System info.');
        $this->createManager($this->rootDir)->reinstall();
    }

    private function command(): SkillsValidateCommand
    {
        return new SkillsValidateCommand($this->createManager($this->rootDir));
    }
}
