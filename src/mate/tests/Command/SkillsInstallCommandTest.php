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
use Symfony\AI\Mate\Command\SkillsInstallCommand;
use Symfony\AI\Mate\Discovery\ComposerExtensionDiscovery;
use Symfony\AI\Mate\Service\SkillsInstaller;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SkillsInstallCommandTest extends TestCase
{
    private Filesystem $filesystem;

    /**
     * @var string[]
     */
    private array $roots = [];

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
    }

    protected function tearDown(): void
    {
        foreach ($this->roots as $root) {
            $this->filesystem->remove($root);
        }
    }

    public function testInstallsSkills()
    {
        $root = $this->createRoot();
        $tester = new CommandTester($this->createCommand($root));

        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Installed 1 skill', $tester->getDisplay());

        $source = $root.'/.agents/skills/mate-demo-skill';
        $this->assertFileExists($source.'/SKILL.md');
        $this->assertTrue(is_link($source));
        $this->assertTrue(is_link($root.'/.claude/skills/mate-demo-skill'));
    }

    public function testReRunSkipsExistingSkill()
    {
        $root = $this->createRoot();

        (new CommandTester($this->createCommand($root)))->execute([]);

        $tester = new CommandTester($this->createCommand($root));
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('No new skills to install', $tester->getDisplay());
    }

    public function testReportsNothingWhenNoSkillsAvailable()
    {
        $root = sys_get_temp_dir().'/mate-skills-command-'.uniqid();
        $this->roots[] = $root;
        $this->filesystem->mkdir($root.'/vendor/composer');
        file_put_contents($root.'/vendor/composer/installed.json', '{"packages": []}');

        $tester = new CommandTester($this->createCommand($root));
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('No new skills to install', $tester->getDisplay());
    }

    public function testInstallsRootProjectSkill()
    {
        $root = sys_get_temp_dir().'/mate-skills-command-'.uniqid();
        $this->roots[] = $root;

        $this->filesystem->mkdir($root.'/vendor/composer');
        file_put_contents($root.'/vendor/composer/installed.json', '{"packages": []}');
        file_put_contents($root.'/composer.json', '{"extra": {"ai-mate": {"skills": ["skills"]}}}');
        $this->filesystem->mkdir($root.'/skills/root-skill');
        file_put_contents($root.'/skills/root-skill/SKILL.md', "---\nname: root-skill\ndescription: demo\n---\n");

        $tester = new CommandTester($this->createCommand($root));
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertFileExists($root.'/.agents/skills/mate-root-skill/SKILL.md');
        $this->assertTrue(is_link($root.'/.claude/skills/mate-root-skill'));
    }

    private function createCommand(string $root): SkillsInstallCommand
    {
        $discovery = new ComposerExtensionDiscovery($root, new NullLogger());
        $installer = new SkillsInstaller($root, new NullLogger(), $this->filesystem, '.agents/skills', ['claude' => '.claude/skills']);

        return new SkillsInstallCommand(
            ['vendor/package-with-skills'],
            $discovery,
            $installer,
        );
    }

    private function createRoot(): string
    {
        $root = sys_get_temp_dir().'/mate-skills-command-'.uniqid();
        $this->roots[] = $root;

        $this->filesystem->mkdir($root.'/vendor/composer');
        file_put_contents($root.'/vendor/composer/installed.json', <<<'JSON'
            {
                "packages": [
                    {
                        "name": "vendor/package-with-skills",
                        "type": "library",
                        "extra": { "ai-mate": { "skills": ["skills"] } }
                    }
                ]
            }
            JSON);

        $skillDir = $root.'/vendor/vendor/package-with-skills/skills/demo-skill';
        $this->filesystem->mkdir($skillDir);
        file_put_contents($skillDir.'/SKILL.md', "---\nname: demo-skill\ndescription: demo\n---\n");

        return $root;
    }
}
