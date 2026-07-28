<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Tests\Skill;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\Mate\Skill\Linker;
use Symfony\AI\Mate\Skill\LinkerInterface;
use Symfony\AI\Mate\Skill\Model\DiscoveredSkill;
use Symfony\AI\Mate\Skill\SkillContentHasher;
use Symfony\AI\Mate\Skill\SkillDiscovery;
use Symfony\AI\Mate\Skill\SkillFrontmatter;
use Symfony\AI\Mate\Skill\SkillInstaller;
use Symfony\AI\Mate\Skill\SkillStateRepository;
use Symfony\AI\Mate\Tests\Skill\Double\FailingLinker;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SkillInstallerTest extends TestCase
{
    use SkillFixtureTrait;

    private string $rootDir;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir().'/mate-skill-installer-'.uniqid();
        mkdir($this->rootDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->rootDir);
    }

    public function testManagedSkillIsCopiedAndRecordsFacts()
    {
        $this->createSkill($this->rootDir.'/vendor/vendor/pkg-a/skills', 'system-information', 'System info.');

        $result = $this->installer()->install($this->discover());

        $this->assertSame(['mate-system-information'], $result->installed);
        $this->assertSame(['mate-system-information'], $result->active);
        $this->assertSame(['mate-system-information' => 'managed'], $result->states);

        $agentsSkill = $this->rootDir.'/.agents/skills/mate-system-information';
        $this->assertDirectoryExists($agentsSkill);
        $this->assertFalse(is_link($agentsSkill), 'The canonical copy must be a real directory, never a symlink into vendor/.');

        $mirror = $this->rootDir.'/.claude/skills/mate-system-information';
        $this->assertTrue(is_link($mirror));
        $this->assertSame('../../.agents/skills/mate-system-information', readlink($mirror));

        $state = $this->state('vendor/pkg-a', 'system-information');
        $this->assertSame('managed', $state['state']);
        $this->assertSame('vendor/vendor/pkg-a/skills/system-information', $state['source']);
        $this->assertStringStartsWith('sha256:', (string) $state['source_hash']);
        $this->assertStringStartsWith('sha256:', (string) $state['hash']);
        $this->assertSame([
            '.agents/skills/mate-system-information',
            '.claude/skills/mate-system-information',
        ], $state['targets']);
    }

    public function testFrontmatterNameIsRewrittenToInstalledName()
    {
        $this->createSkill($this->rootDir.'/vendor/vendor/pkg-a/skills', 'system-information', 'System info.');

        $this->installer()->install($this->discover());

        $content = file_get_contents($this->rootDir.'/.agents/skills/mate-system-information/SKILL.md');
        $this->assertIsString($content);
        $this->assertStringContainsString('name: mate-system-information', $content);

        // The vendor source is never touched.
        $source = file_get_contents($this->rootDir.'/vendor/vendor/pkg-a/skills/system-information/SKILL.md');
        $this->assertIsString($source);
        $this->assertStringContainsString('name: system-information', $source);
    }

    public function testReinstallIsIdempotent()
    {
        $this->createSkill($this->rootDir.'/vendor/vendor/pkg-a/skills', 'system-information', 'System info.');

        $this->installer()->install($this->discover());
        $afterFirst = file_get_contents($this->rootDir.'/mate/extensions.php');

        $second = $this->installer()->install($this->discover());

        $this->assertSame([], $second->installed);
        $this->assertSame([], $second->removed);
        $this->assertSame(['mate-system-information'], $second->active);
        $this->assertSame($afterFirst, file_get_contents($this->rootDir.'/mate/extensions.php'));
    }

    public function testHandEditedTargetIsRebuilt()
    {
        $this->createSkill($this->rootDir.'/vendor/vendor/pkg-a/skills', 'system-information', 'System info.', 'ORIGINAL');
        $this->installer()->install($this->discover());

        $installedFile = $this->rootDir.'/.agents/skills/mate-system-information/SKILL.md';
        file_put_contents($installedFile, "---\nname: mate-system-information\ndescription: tampered\n---\n\nTAMPERED");

        $this->installer()->install($this->discover());

        $content = file_get_contents($installedFile);
        $this->assertIsString($content);
        $this->assertStringContainsString('ORIGINAL', $content);
        $this->assertStringNotContainsString('TAMPERED', $content);
    }

    public function testSourceChangeTriggersRebuild()
    {
        $skillsDir = $this->rootDir.'/vendor/vendor/pkg-a/skills';
        $this->createSkill($skillsDir, 'system-information', 'System info.', 'FIRST');
        $this->installer()->install($this->discover());
        $firstHash = $this->state('vendor/pkg-a', 'system-information')['source_hash'];

        $this->createSkill($skillsDir, 'system-information', 'System info.', 'SECOND');
        $this->installer()->install($this->discover());

        $content = file_get_contents($this->rootDir.'/.agents/skills/mate-system-information/SKILL.md');
        $this->assertIsString($content);
        $this->assertStringContainsString('SECOND', $content);
        $this->assertNotSame($firstHash, $this->state('vendor/pkg-a', 'system-information')['source_hash']);
    }

    public function testClaudeMirrorFallsBackToCopyWhenLinkerFails()
    {
        $this->createSkill($this->rootDir.'/vendor/vendor/pkg-a/skills', 'system-information', 'System info.');

        $result = $this->installer(new FailingLinker())->install($this->discover());

        $mirror = $this->rootDir.'/.claude/skills/mate-system-information';
        $this->assertFalse(is_link($mirror));
        $this->assertDirectoryExists($mirror);
        $this->assertFileExists($mirror.'/SKILL.md');
        $this->assertCount(1, $result->notices);
        $this->assertStringContainsString('symlinks are unavailable', $result->notices[0]);
    }

    public function testVanishedSkillIsAutoPruned()
    {
        $this->createSkill($this->rootDir.'/vendor/vendor/pkg-a/skills', 'system-information', 'System info.');
        $this->installer()->install($this->discover());

        $this->removeDirectory($this->rootDir.'/vendor/vendor/pkg-a');

        $result = $this->installer()->install($this->discover());

        $this->assertSame(['mate-system-information'], $result->removed);
        $this->assertSame([], $result->active);
        $this->assertDirectoryDoesNotExist($this->rootDir.'/.agents/skills/mate-system-information');
        $this->assertFalse(is_link($this->rootDir.'/.claude/skills/mate-system-information'));

        $config = (new SkillStateRepository($this->rootDir))->read();
        $this->assertArrayNotHasKey('skills', $config['vendor/pkg-a'] ?? []);
    }

    public function testDisabledSkillRemovesTargetsAndRecordsDisabledState()
    {
        $this->createSkill($this->rootDir.'/vendor/vendor/pkg-a/skills', 'system-information', 'System info.');
        $this->installer()->install($this->discover());

        (new SkillStateRepository($this->rootDir))->setEnabled('vendor/pkg-a', 'system-information', false);

        $result = $this->installer()->install($this->discover());

        $this->assertSame([], $result->active);
        $this->assertDirectoryDoesNotExist($this->rootDir.'/.agents/skills/mate-system-information');
        $this->assertFalse(is_link($this->rootDir.'/.claude/skills/mate-system-information'));

        $state = $this->state('vendor/pkg-a', 'system-information');
        $this->assertSame('disabled', $state['state']);
        $this->assertSame([], $state['targets']);
        $this->assertNull($state['hash']);
    }

    public function testDisabledExtensionKeepsSkillOutOfGeneratedFolders()
    {
        $this->createSkill($this->rootDir.'/vendor/vendor/pkg-a/skills', 'system-information', 'System info.');
        (new SkillStateRepository($this->rootDir))->write(['vendor/pkg-a' => ['enabled' => false]]);

        $result = $this->installer()->install($this->discover());

        $this->assertSame([], $result->active);
        $this->assertDirectoryDoesNotExist($this->rootDir.'/.agents/skills/mate-system-information');
    }

    public function testOverrideBuildsFromUserCopyAndLeavesItUntouched()
    {
        $this->createSkill($this->rootDir.'/vendor/vendor/pkg-a/skills', 'system-information', 'Vendor body.', 'VENDOR CONTENT');
        $this->createSkill($this->rootDir.'/mate/skills', 'system-information', 'Overridden.', 'OVERRIDE CONTENT');
        (new SkillStateRepository($this->rootDir))->setMode('vendor/pkg-a', 'system-information', 'override');

        $result = $this->installer()->install($this->discover());

        $this->assertSame(['mate-system-information'], $result->active);
        $this->assertSame(['mate-system-information' => 'override'], $result->states);

        $generated = file_get_contents($this->rootDir.'/.agents/skills/mate-system-information/SKILL.md');
        $this->assertIsString($generated);
        $this->assertStringContainsString('OVERRIDE CONTENT', $generated);
        $this->assertStringNotContainsString('VENDOR CONTENT', $generated);

        // The override source is user-owned and must never be rewritten by the installer.
        $override = file_get_contents($this->rootDir.'/mate/skills/system-information/SKILL.md');
        $this->assertIsString($override);
        $this->assertStringContainsString('name: system-information', $override);

        $state = $this->state('vendor/pkg-a', 'system-information');
        $this->assertSame('override', $state['state']);
        $this->assertSame('mate/skills/system-information', $state['source']);
    }

    public function testOverrideWithMissingUserCopyIsSkipped()
    {
        $this->createSkill($this->rootDir.'/vendor/vendor/pkg-a/skills', 'system-information', 'Vendor body.');
        (new SkillStateRepository($this->rootDir))->setMode('vendor/pkg-a', 'system-information', 'override');

        $result = $this->installer()->install($this->discover());

        $this->assertArrayHasKey('mate-system-information', $result->skipped);
        $this->assertSame([], $result->active);
    }

    public function testGitignoreIsLeftAlone()
    {
        $this->createSkill($this->rootDir.'/vendor/vendor/pkg-a/skills', 'system-information', 'System info.');
        file_put_contents($this->rootDir.'/.gitignore', "/vendor/\n");

        $this->installer()->install($this->discover());

        // The generated folders are plain copies and meant to be committed, so nothing is ignored.
        $this->assertSame("/vendor/\n", file_get_contents($this->rootDir.'/.gitignore'));
    }

    public function testStraysArePrunedOnInstall()
    {
        $this->createSkill($this->rootDir.'/vendor/vendor/pkg-a/skills', 'system-information', 'System info.');
        mkdir($this->rootDir.'/.agents/skills/mate-left-over', 0777, true);
        mkdir($this->rootDir.'/.agents/skills/my-own-skill', 0777, true);

        $result = $this->installer()->install($this->discover());

        $this->assertContains('mate-left-over', $result->removed);
        $this->assertDirectoryDoesNotExist($this->rootDir.'/.agents/skills/mate-left-over');
        $this->assertDirectoryExists($this->rootDir.'/.agents/skills/my-own-skill');
    }

    /**
     * @return array<string, mixed>
     */
    private function state(string $package, string $name): array
    {
        $config = (new SkillStateRepository($this->rootDir))->read();

        return $config[$package]['skills'][$name];
    }

    /**
     * @return list<DiscoveredSkill>
     */
    private function discover(): array
    {
        $discovery = new SkillDiscovery($this->rootDir, new SkillFrontmatter(), new NullLogger());

        return $discovery->discover([
            'vendor/pkg-a' => ['dirs' => [], 'includes' => [], 'skills' => ['vendor/vendor/pkg-a/skills']],
        ]);
    }

    private function installer(?LinkerInterface $linker = null): SkillInstaller
    {
        return new SkillInstaller(
            $this->rootDir,
            new SkillStateRepository($this->rootDir),
            new SkillFrontmatter(),
            new SkillContentHasher(),
            $linker ?? new Linker(),
            new NullLogger(),
        );
    }
}
