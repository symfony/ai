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
use Symfony\AI\Mate\Command\SkillsDisableCommand;
use Symfony\AI\Mate\Command\SkillsEnableCommand;
use Symfony\AI\Mate\Skill\SkillStateRepository;
use Symfony\AI\Mate\Tests\Skill\SkillFixtureTrait;
use Symfony\AI\Mate\Tests\Skill\SkillServicesTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SkillsEnableCommandTest extends TestCase
{
    use SkillFixtureTrait;
    use SkillServicesTrait;

    private string $rootDir;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir().'/mate-skills-enable-cmd-'.uniqid();
        mkdir($this->rootDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->rootDir);
    }

    public function testDisableThenEnableRestoresBothTargets()
    {
        $this->installFixture();
        (new CommandTester(new SkillsDisableCommand($this->createManager($this->rootDir))))
            ->execute(['name' => 'system-information']);
        $this->assertDirectoryDoesNotExist($this->rootDir.'/.agents/skills/mate-system-information');

        $tester = new CommandTester($this->command());
        $tester->execute(['name' => 'mate-system-information']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertDirectoryExists($this->rootDir.'/.agents/skills/mate-system-information');
        $this->assertTrue(is_link($this->rootDir.'/.claude/skills/mate-system-information'));

        $state = (new SkillStateRepository($this->rootDir))->read()['vendor/pkg-a']['skills']['system-information'];
        $this->assertTrue($state['enabled']);
        $this->assertSame('managed', $state['state']);
    }

    public function testResolvesTheUnprefixedName()
    {
        $this->installFixture();
        (new CommandTester(new SkillsDisableCommand($this->createManager($this->rootDir))))
            ->execute(['name' => 'mate-system-information']);

        $tester = new CommandTester($this->command());
        $tester->execute(['name' => 'system-information']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertDirectoryExists($this->rootDir.'/.agents/skills/mate-system-information');
    }

    public function testWarnsWhenTheOwningExtensionIsDisabled()
    {
        $this->installFixture();
        (new CommandTester(new SkillsDisableCommand($this->createManager($this->rootDir))))
            ->execute(['name' => 'system-information']);
        $this->disableExtension();

        $tester = new CommandTester($this->command());
        $tester->execute(['name' => 'system-information']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('stays hidden', $tester->getDisplay());
        $this->assertDirectoryDoesNotExist($this->rootDir.'/.agents/skills/mate-system-information');
    }

    public function testIsANoOpForAnAlreadyEnabledSkill()
    {
        $this->installFixture();
        $installed = $this->rootDir.'/.agents/skills/mate-system-information/SKILL.md';
        file_put_contents($installed, 'HAND EDITED');

        $tester = new CommandTester($this->command());
        $tester->execute(['name' => 'system-information']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('already enabled', $tester->getDisplay());
        $this->assertSame('HAND EDITED', file_get_contents($installed));
    }

    public function testTheNoOpStillPointsAtTheDisabledExtension()
    {
        $this->installFixture();
        $this->disableExtension();

        $tester = new CommandTester($this->command());
        $tester->execute(['name' => 'system-information']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('already enabled', $tester->getDisplay());
        $this->assertStringContainsString('stays hidden', $tester->getDisplay());
    }

    public function testFailsOnUnknownName()
    {
        $this->installFixture();

        $tester = new CommandTester($this->command());
        $tester->execute(['name' => 'nope']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    private function installFixture(): void
    {
        $this->createInstalledPackage($this->rootDir);
        $this->createSkill($this->rootDir.'/vendor/vendor/pkg-a/skills', 'system-information', 'System info.');
        $this->createManager($this->rootDir)->reinstall();
    }

    private function disableExtension(): void
    {
        $repository = new SkillStateRepository($this->rootDir);
        $config = $repository->read();
        $config['vendor/pkg-a']['enabled'] = false;
        $repository->write($config);
    }

    private function command(): SkillsEnableCommand
    {
        return new SkillsEnableCommand($this->createManager($this->rootDir));
    }
}
