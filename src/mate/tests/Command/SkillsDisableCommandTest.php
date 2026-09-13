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
use Symfony\AI\Mate\Command\SkillsOverrideCommand;
use Symfony\AI\Mate\Skill\SkillStateRepository;
use Symfony\AI\Mate\Tests\Skill\SkillFixtureTrait;
use Symfony\AI\Mate\Tests\Skill\SkillServicesTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SkillsDisableCommandTest extends TestCase
{
    use SkillFixtureTrait;
    use SkillServicesTrait;

    private string $rootDir;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir().'/mate-skills-disable-cmd-'.uniqid();
        mkdir($this->rootDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->rootDir);
    }

    public function testRemovesTheGeneratedFoldersAndRecordsDisabled()
    {
        $this->installFixture();

        $tester = new CommandTester($this->command());
        $tester->execute(['name' => 'mate-system-information']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertDirectoryDoesNotExist($this->rootDir.'/.agents/skills/mate-system-information');
        $this->assertFalse(is_link($this->rootDir.'/.claude/skills/mate-system-information'));

        $state = (new SkillStateRepository($this->rootDir))->read()['vendor/pkg-a']['skills']['system-information'];
        $this->assertFalse($state['enabled']);
        $this->assertSame('disabled', $state['state']);
        $this->assertSame([], $state['targets']);
        $this->assertNull($state['hash']);
    }

    public function testKeepsTheEntrySoItCanBeEnabledAgain()
    {
        $this->installFixture();

        (new CommandTester($this->command()))->execute(['name' => 'system-information']);

        $config = (new SkillStateRepository($this->rootDir))->read();
        $this->assertArrayHasKey('system-information', $config['vendor/pkg-a']['skills']);
    }

    public function testLeavesAnOverrideCopyAlone()
    {
        $this->installFixture();
        (new CommandTester(new SkillsOverrideCommand($this->createManager($this->rootDir))))
            ->execute(['name' => 'system-information']);

        (new CommandTester($this->command()))->execute(['name' => 'system-information']);

        $this->assertDirectoryExists($this->rootDir.'/mate/skills/system-information');
    }

    public function testDisabledSkillStaysDisabledAcrossReinstalls()
    {
        $this->installFixture();
        (new CommandTester($this->command()))->execute(['name' => 'system-information']);

        $this->createManager($this->rootDir)->reinstall();

        $this->assertDirectoryDoesNotExist($this->rootDir.'/.agents/skills/mate-system-information');
    }

    public function testIsANoOpForAnAlreadyDisabledSkill()
    {
        $this->installFixture();
        (new CommandTester($this->command()))->execute(['name' => 'system-information']);

        // A second run must not reconcile anything: a hand-made folder that a reinstall would prune
        // as a stray is the cheapest proof that the command kept its hands off the filesystem.
        $stray = $this->rootDir.'/.agents/skills/mate-system-information';
        mkdir($stray, 0777, true);

        $tester = new CommandTester($this->command());
        $tester->execute(['name' => 'system-information']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('already disabled', $tester->getDisplay());
        $this->assertDirectoryExists($stray);
    }

    public function testDisablesASkillThatIsOnlyHiddenByItsExtension()
    {
        $this->installFixture();

        $repository = new SkillStateRepository($this->rootDir);
        $config = $repository->read();
        $config['vendor/pkg-a']['enabled'] = false;
        $repository->write($config);

        $tester = new CommandTester($this->command());
        $tester->execute(['name' => 'system-information']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringNotContainsString('already disabled', $tester->getDisplay());

        $state = (new SkillStateRepository($this->rootDir))->read()['vendor/pkg-a']['skills']['system-information'];
        $this->assertFalse($state['enabled']);
    }

    public function testFailsOnUnknownName()
    {
        $this->installFixture();

        $tester = new CommandTester($this->command());
        $tester->execute(['name' => 'nope']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Unknown skill', $tester->getDisplay());
    }

    private function installFixture(): void
    {
        $this->createInstalledPackage($this->rootDir);
        $this->createSkill($this->rootDir.'/vendor/vendor/pkg-a/skills', 'system-information', 'System info.');
        $this->createManager($this->rootDir)->reinstall();
    }

    private function command(): SkillsDisableCommand
    {
        return new SkillsDisableCommand($this->createManager($this->rootDir));
    }
}
