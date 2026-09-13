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
use Symfony\AI\Mate\Skill\SkillStateRepository;
use Symfony\AI\Mate\Tests\Skill\SkillFixtureTrait;
use Symfony\AI\Mate\Tests\Skill\SkillServicesTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SkillsOverrideCommandTest extends TestCase
{
    use SkillFixtureTrait;
    use SkillServicesTrait;

    private string $rootDir;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir().'/mate-skills-override-cmd-'.uniqid();
        mkdir($this->rootDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->rootDir);
    }

    public function testTakesOwnershipOfASkill()
    {
        $this->installFixture();

        $tester = new CommandTester($this->command());
        $tester->execute(['name' => 'mate-system-information']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        // The user's copy keeps the original name; the mate- prefix is applied at build time.
        $copy = file_get_contents($this->rootDir.'/mate/skills/system-information/SKILL.md');
        $this->assertIsString($copy);
        $this->assertStringContainsString('name: system-information', $copy);

        $installed = file_get_contents($this->rootDir.'/.agents/skills/mate-system-information/SKILL.md');
        $this->assertIsString($installed);
        $this->assertStringContainsString('name: mate-system-information', $installed);

        $state = (new SkillStateRepository($this->rootDir))->read()['vendor/pkg-a']['skills']['system-information'];
        $this->assertSame('override', $state['mode']);
        $this->assertSame('override', $state['state']);
        $this->assertSame('mate/skills/system-information', $state['source']);
    }

    public function testEditsToTheCopyLandInTheGeneratedFolder()
    {
        $this->installFixture();
        (new CommandTester($this->command()))->execute(['name' => 'system-information']);

        file_put_contents(
            $this->rootDir.'/mate/skills/system-information/SKILL.md',
            "---\nname: system-information\ndescription: Mine now.\n---\n\nMY OWN CONTENT",
        );
        $this->createManager($this->rootDir)->reinstall();

        $installed = file_get_contents($this->rootDir.'/.agents/skills/mate-system-information/SKILL.md');
        $this->assertIsString($installed);
        $this->assertStringContainsString('MY OWN CONTENT', $installed);
        $this->assertStringContainsString('name: mate-system-information', $installed);
    }

    public function testRefusesWhenAlreadyOverriddenWithoutForce()
    {
        $this->installFixture();
        (new CommandTester($this->command()))->execute(['name' => 'system-information']);

        $tester = new CommandTester($this->command());
        $tester->execute(['name' => 'system-information']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('already overridden', $tester->getDisplay());
    }

    public function testForceReplacesAnExistingCopy()
    {
        $this->installFixture();
        (new CommandTester($this->command()))->execute(['name' => 'system-information']);
        file_put_contents($this->rootDir.'/mate/skills/system-information/SKILL.md', 'STALE');

        $tester = new CommandTester($this->command());
        $tester->execute(['name' => 'system-information', '--force' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $copy = file_get_contents($this->rootDir.'/mate/skills/system-information/SKILL.md');
        $this->assertIsString($copy);
        $this->assertStringNotContainsString('STALE', $copy);
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

    private function command(): SkillsOverrideCommand
    {
        return new SkillsOverrideCommand($this->createManager($this->rootDir));
    }
}
