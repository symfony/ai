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
use Symfony\AI\Mate\Command\SkillsOverrideCommand;
use Symfony\AI\Mate\Command\SkillsResetCommand;
use Symfony\AI\Mate\Skill\SkillStateRepository;
use Symfony\AI\Mate\Tests\Skill\SkillFixtureTrait;
use Symfony\AI\Mate\Tests\Skill\SkillServicesTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SkillsResetCommandTest extends TestCase
{
    use SkillFixtureTrait;
    use SkillServicesTrait;

    private string $rootDir;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir().'/mate-skills-reset-cmd-'.uniqid();
        mkdir($this->rootDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->rootDir);
    }

    public function testHandsTheSkillBackToMateAndRebuildsFromThePackage()
    {
        $this->overrideFixture('MY OWN CONTENT');

        $tester = new CommandTester($this->command());
        $tester->execute(['name' => 'mate-system-information']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $state = (new SkillStateRepository($this->rootDir))->read()['vendor/pkg-a']['skills']['system-information'];
        $this->assertSame('managed', $state['mode']);
        $this->assertSame('managed', $state['state']);
        $this->assertSame('vendor/vendor/pkg-a/skills/system-information', $state['source']);

        $installed = file_get_contents($this->rootDir.'/.agents/skills/mate-system-information/SKILL.md');
        $this->assertIsString($installed);
        $this->assertStringNotContainsString('MY OWN CONTENT', $installed);
        $this->assertStringContainsString('VENDOR CONTENT', $installed);
    }

    public function testKeepsTheLocalCopyByDefaultAndSaysWhereItIs()
    {
        $this->overrideFixture('MY OWN CONTENT');

        $tester = new CommandTester($this->command());
        $tester->execute(['name' => 'system-information']);

        $this->assertDirectoryExists($this->rootDir.'/mate/skills/system-information');
        $this->assertStringContainsString('mate/skills/system-information', $tester->getDisplay());
    }

    public function testDeleteCopyRemovesIt()
    {
        $this->overrideFixture('MY OWN CONTENT');

        $tester = new CommandTester($this->command());
        $tester->execute(['name' => 'system-information', '--delete-copy' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertDirectoryDoesNotExist($this->rootDir.'/mate/skills/system-information');
    }

    public function testIsANoOpForAnAlreadyManagedSkill()
    {
        $this->installFixture();

        $tester = new CommandTester($this->command());
        $tester->execute(['name' => 'system-information']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('already managed', $tester->getDisplay());
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
        $this->createSkill($this->rootDir.'/vendor/vendor/pkg-a/skills', 'system-information', 'System info.', 'VENDOR CONTENT');
        $this->createManager($this->rootDir)->reinstall();
    }

    private function overrideFixture(string $ownContent): void
    {
        $this->installFixture();
        (new CommandTester(new SkillsOverrideCommand($this->createManager($this->rootDir))))
            ->execute(['name' => 'system-information']);

        file_put_contents(
            $this->rootDir.'/mate/skills/system-information/SKILL.md',
            "---\nname: system-information\ndescription: Mine now.\n---\n\n".$ownContent,
        );
        $this->createManager($this->rootDir)->reinstall();
    }

    private function command(): SkillsResetCommand
    {
        return new SkillsResetCommand($this->createManager($this->rootDir));
    }
}
