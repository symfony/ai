<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Tests\Service;

use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;
use Symfony\AI\Mate\Service\SkillsInstaller;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SkillsInstallerTest extends TestCase
{
    private string $rootDir;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->rootDir = sys_get_temp_dir().'/mate-skills-installer-'.uniqid();

        // Vendor skill tree: one valid skill (with a reference) and one junk dir without SKILL.md.
        $skillsDir = $this->rootDir.'/vendor/vendor/pkg/skills';
        $this->filesystem->mkdir($skillsDir.'/demo-skill/references');
        file_put_contents($skillsDir.'/demo-skill/SKILL.md', "---\nname: demo-skill\ndescription: demo\n---\n");
        file_put_contents($skillsDir.'/demo-skill/references/collectors.md', "# ref\n");
        $this->filesystem->mkdir($skillsDir.'/not-a-skill');
        file_put_contents($skillsDir.'/not-a-skill/readme.md', "not a skill\n");
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->rootDir);
    }

    public function testLinksSourceAndMirrorUnderPrefixedName()
    {
        $installer = $this->createInstaller();

        $result = $installer->install($this->extensions());

        $this->assertSame(['mate-demo-skill'], $result['installed']);

        $source = $this->rootDir.'/.agents/skills/mate-demo-skill';
        $this->assertTrue(is_link($source), 'Source-of-truth entry is symlinked into vendor');
        $this->assertFileExists($source.'/SKILL.md');
        $this->assertFileExists($source.'/references/collectors.md');

        $this->assertDirectoryDoesNotExist($this->rootDir.'/.agents/skills/mate-not-a-skill');

        $mirror = $this->rootDir.'/.claude/skills/mate-demo-skill';
        $this->assertTrue(is_link($mirror), 'Claude mirror is a symlink');
        $this->assertFileExists($mirror.'/SKILL.md');
    }

    public function testReRunSkipsExistingSkill()
    {
        $installer = $this->createInstaller();

        $first = $installer->install($this->extensions());
        $this->assertSame(['mate-demo-skill'], $first['installed']);

        $second = $installer->install($this->extensions());
        $this->assertSame([], $second['installed'], 'Already-installed skills are not reinstalled');
    }

    public function testBrokenMirrorSymlinkIsRepaired()
    {
        $installer = $this->createInstaller();
        $installer->install($this->extensions());

        // Corrupt the mirror so it dangles (points at a non-existent target).
        $mirror = $this->rootDir.'/.claude/skills/mate-demo-skill';
        $this->filesystem->remove($mirror);
        symlink('./does-not-exist', $mirror);
        $this->assertFalse(file_exists($mirror), 'Mirror is dangling before repair');

        // A plain re-run heals the dangling link.
        $installer->install($this->extensions());

        $this->assertTrue(is_link($mirror));
        $this->assertFileExists($mirror.'/SKILL.md', 'Mirror resolves to the installed skill again');
    }

    public function testCrossExtensionNameCollisionFirstWins()
    {
        $this->writeSkill('vendor/vendor/pkg-a/skills', 'shared', "from-a\n");
        $this->writeSkill('vendor/vendor/pkg-b/skills', 'shared', "from-b\n");

        $installer = $this->createInstaller();
        $result = $installer->install([
            'vendor/pkg-a' => ['dirs' => [], 'includes' => [], 'skills' => ['vendor/vendor/pkg-a/skills']],
            'vendor/pkg-b' => ['dirs' => [], 'includes' => [], 'skills' => ['vendor/vendor/pkg-b/skills']],
        ]);

        $this->assertSame(['mate-shared'], $result['installed']);
        $this->assertStringEqualsFile($this->rootDir.'/.agents/skills/mate-shared/SKILL.md', "from-a\n");
    }

    public function testMultipleSkillsFromOneExtension()
    {
        $this->writeSkill('vendor/vendor/multi/skills', 'one', "one\n");
        $this->writeSkill('vendor/vendor/multi/skills', 'two', "two\n");

        $installer = $this->createInstaller();
        $result = $installer->install([
            'vendor/multi' => ['dirs' => [], 'includes' => [], 'skills' => ['vendor/vendor/multi/skills']],
        ]);

        sort($result['installed']);
        $this->assertSame(['mate-one', 'mate-two'], $result['installed']);
        $this->assertDirectoryExists($this->rootDir.'/.agents/skills/mate-one');
        $this->assertDirectoryExists($this->rootDir.'/.agents/skills/mate-two');
        $this->assertTrue(is_link($this->rootDir.'/.claude/skills/mate-one'));
        $this->assertTrue(is_link($this->rootDir.'/.claude/skills/mate-two'));
    }

    public function testMissingSkillsDirectoryIsIgnored()
    {
        $installer = $this->createInstaller();

        $result = $installer->install([
            'vendor/ghost' => ['dirs' => [], 'includes' => [], 'skills' => ['vendor/vendor/ghost/skills']],
        ]);

        $this->assertSame([], $result['installed']);
        $this->assertDirectoryDoesNotExist($this->rootDir.'/.agents/skills');
    }

    public function testRootProjectSkillsAreInstalled()
    {
        $this->writeSkill('skills', 'root-skill', "root\n");

        $installer = $this->createInstaller();
        $result = $installer->install([
            '_custom' => ['dirs' => [], 'includes' => [], 'skills' => ['skills']],
        ]);

        $this->assertSame(['mate-root-skill'], $result['installed']);
        $this->assertFileExists($this->rootDir.'/.agents/skills/mate-root-skill/SKILL.md');
        $this->assertTrue(is_link($this->rootDir.'/.claude/skills/mate-root-skill'));
    }

    public function testRejectsSkillContainingSymlink()
    {
        // A malicious package ships a real skill dir whose tree hides a symlink pointing outside
        // its own directory; installing it would surface the target to the coding agent.
        $skillDir = $this->rootDir.'/vendor/vendor/evilpkg/skills/evil';
        $this->filesystem->mkdir($skillDir);
        file_put_contents($skillDir.'/SKILL.md', "---\nname: evil\ndescription: x\n---\n");
        $secret = $this->rootDir.'/secret.txt';
        file_put_contents($secret, "TOP SECRET\n");
        symlink($secret, $skillDir.'/leak.txt');

        $installer = $this->createInstaller();
        $result = $installer->install([
            'vendor/evilpkg' => ['dirs' => [], 'includes' => [], 'skills' => ['vendor/vendor/evilpkg/skills']],
        ]);

        $this->assertSame([], $result['installed']);
        $this->assertDirectoryDoesNotExist($this->rootDir.'/.agents/skills/mate-evil');
    }

    public function testStaleSkillLinksArePruned()
    {
        $installer = $this->createInstaller();
        $installer->install($this->extensions());

        $source = $this->rootDir.'/.agents/skills/mate-demo-skill';
        $mirror = $this->rootDir.'/.claude/skills/mate-demo-skill';
        $this->assertTrue(is_link($source));
        $this->assertTrue(is_link($mirror));

        // The extension (and thus the skill) is no longer discovered on a subsequent run.
        $installer->install([]);

        $this->assertFalse(is_link($source), 'Stale source link is pruned');
        $this->assertFalse(is_link($mirror), 'Stale mirror link is pruned');
    }

    public function testMirrorLeavesUserOwnedEntryUntouched()
    {
        $userDir = $this->rootDir.'/.claude/skills/mate-demo-skill';
        $this->filesystem->mkdir($userDir);
        file_put_contents($userDir.'/user.txt', "mine\n");

        $installer = $this->createInstaller();
        $installer->install($this->extensions());

        $this->assertFalse(is_link($userDir), 'A real user-owned mirror entry is never replaced by a symlink');
        $this->assertStringEqualsFile($userDir.'/user.txt', "mine\n");
    }

    public function testInstalledSymlinksAreRelative()
    {
        $installer = $this->createInstaller();
        $installer->install($this->extensions());

        $source = $this->rootDir.'/.agents/skills/mate-demo-skill';
        $mirror = $this->rootDir.'/.claude/skills/mate-demo-skill';

        // Relative targets keep the committed .agents/.claude trees portable across machines.
        $this->assertStringStartsWith('../', readlink($source));
        $this->assertStringContainsString('vendor/vendor/pkg/skills/demo-skill', readlink($source));
        $this->assertStringStartsWith('../', readlink($mirror));
        $this->assertStringContainsString('.agents/skills/mate-demo-skill', readlink($mirror));
    }

    public function testDanglingSourceLinkIsRepaired()
    {
        $installer = $this->createInstaller();
        $installer->install($this->extensions());

        // Corrupt the source-of-truth link so it dangles.
        $source = $this->rootDir.'/.agents/skills/mate-demo-skill';
        $this->filesystem->remove($source);
        symlink('./does-not-exist', $source);
        $this->assertFalse(file_exists($source), 'Source link is dangling before repair');

        $installer->install($this->extensions());

        $this->assertTrue(is_link($source));
        $this->assertFileExists($source.'/SKILL.md', 'Source link resolves to the installed skill again');
    }

    public function testDuplicateSkillNameLogsWarning()
    {
        $this->writeSkill('vendor/vendor/pkg-a/skills', 'shared', "from-a\n");
        $this->writeSkill('vendor/vendor/pkg-b/skills', 'shared', "from-b\n");

        $logger = new class extends AbstractLogger {
            /**
             * @var list<array{mixed, string, array<string, mixed>}>
             */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = [$level, (string) $message, $context];
            }
        };

        $installer = new SkillsInstaller($this->rootDir, $logger, $this->filesystem, '.agents/skills', ['claude' => '.claude/skills']);
        $installer->install([
            'vendor/pkg-a' => ['dirs' => [], 'includes' => [], 'skills' => ['vendor/vendor/pkg-a/skills']],
            'vendor/pkg-b' => ['dirs' => [], 'includes' => [], 'skills' => ['vendor/vendor/pkg-b/skills']],
        ]);

        $warnedForSharedSkill = false;
        foreach ($logger->records as [, , $context]) {
            if ('shared' === ($context['skill'] ?? null)) {
                $warnedForSharedSkill = true;
                break;
            }
        }

        $this->assertTrue($warnedForSharedSkill, 'A cross-extension name collision is logged with the skill name');
    }

    /**
     * @param array<string, string> $mirrors
     */
    private function createInstaller(array $mirrors = ['claude' => '.claude/skills']): SkillsInstaller
    {
        return new SkillsInstaller($this->rootDir, new NullLogger(), $this->filesystem, '.agents/skills', $mirrors);
    }

    private function writeSkill(string $skillsRelDir, string $name, string $body): void
    {
        $dir = $this->rootDir.'/'.$skillsRelDir.'/'.$name;
        $this->filesystem->mkdir($dir);
        file_put_contents($dir.'/SKILL.md', $body);
    }

    /**
     * @return array<string, array{dirs: string[], includes: string[], skills: string[]}>
     */
    private function extensions(): array
    {
        return [
            'vendor/pkg' => [
                'dirs' => [],
                'includes' => [],
                'skills' => ['vendor/vendor/pkg/skills'],
            ],
        ];
    }
}
