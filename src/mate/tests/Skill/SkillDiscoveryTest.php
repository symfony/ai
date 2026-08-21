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
use Symfony\AI\Mate\Exception\SkillCollisionException;
use Symfony\AI\Mate\Skill\SkillDiscovery;
use Symfony\AI\Mate\Skill\SkillFrontmatter;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SkillDiscoveryTest extends TestCase
{
    use SkillFixtureTrait;

    private string $rootDir;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir().'/mate-skill-discovery-'.uniqid();
        mkdir($this->rootDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->rootDir);
    }

    public function testDiscoversPackageAndRootSkills()
    {
        $this->createSkill($this->rootDir.'/vendor/vendor/pkg-a/skills', 'system-information', 'System info.');
        $this->createSkill($this->rootDir.'/mate/skills', 'project-notes', 'Project notes.');

        $skills = $this->discovery()->discover([
            'vendor/pkg-a' => ['dirs' => [], 'includes' => [], 'skills' => ['vendor/vendor/pkg-a/skills']],
            '_custom' => ['dirs' => [], 'includes' => [], 'skills' => ['mate/skills']],
        ]);

        $this->assertCount(2, $skills);

        $byInstalledName = [];
        foreach ($skills as $skill) {
            $byInstalledName[$skill->installedName] = $skill;
        }

        $this->assertArrayHasKey('mate-system-information', $byInstalledName);
        $this->assertSame('vendor/pkg-a', $byInstalledName['mate-system-information']->package);
        $this->assertSame('system-information', $byInstalledName['mate-system-information']->originalName);
        $this->assertSame('vendor/vendor/pkg-a/skills/system-information', $byInstalledName['mate-system-information']->source);

        $this->assertArrayHasKey('mate-project-notes', $byInstalledName);
        $this->assertSame('_custom', $byInstalledName['mate-project-notes']->package);
        $this->assertSame('mate/skills/project-notes', $byInstalledName['mate-project-notes']->source);
    }

    public function testSkipsSkillWhenFrontmatterNameDoesNotMatchDirectory()
    {
        $skillDir = $this->rootDir.'/vendor/vendor/pkg-a/skills/system-information';
        mkdir($skillDir, 0777, true);
        file_put_contents($skillDir.'/SKILL.md', "---\nname: wrong-name\ndescription: Mismatch.\n---\nBody.\n");

        $skills = $this->discovery()->discover([
            'vendor/pkg-a' => ['dirs' => [], 'includes' => [], 'skills' => ['vendor/vendor/pkg-a/skills']],
        ]);

        $this->assertSame([], $skills);
    }

    public function testSkipsSkillWithoutDescription()
    {
        $skillDir = $this->rootDir.'/vendor/vendor/pkg-a/skills/system-information';
        mkdir($skillDir, 0777, true);
        file_put_contents($skillDir.'/SKILL.md', "---\nname: system-information\n---\nBody.\n");

        $skills = $this->discovery()->discover([
            'vendor/pkg-a' => ['dirs' => [], 'includes' => [], 'skills' => ['vendor/vendor/pkg-a/skills']],
        ]);

        $this->assertSame([], $skills);
    }

    public function testSkipsDirectoryWithoutSkillFile()
    {
        mkdir($this->rootDir.'/vendor/vendor/pkg-a/skills/not-a-skill', 0777, true);

        $skills = $this->discovery()->discover([
            'vendor/pkg-a' => ['dirs' => [], 'includes' => [], 'skills' => ['vendor/vendor/pkg-a/skills']],
        ]);

        $this->assertSame([], $skills);
    }

    public function testThrowsOnInstalledNameCollisionBetweenPackages()
    {
        $this->createSkill($this->rootDir.'/vendor/vendor/pkg-a/skills', 'system-information', 'From A.');
        $this->createSkill($this->rootDir.'/vendor/vendor/pkg-b/skills', 'system-information', 'From B.');

        $this->expectException(SkillCollisionException::class);
        $this->expectExceptionMessage('mate-system-information');

        $this->discovery()->discover([
            'vendor/pkg-a' => ['dirs' => [], 'includes' => [], 'skills' => ['vendor/vendor/pkg-a/skills']],
            'vendor/pkg-b' => ['dirs' => [], 'includes' => [], 'skills' => ['vendor/vendor/pkg-b/skills']],
        ]);
    }

    public function testIgnoresExtensionsWithoutDeclaredSkills()
    {
        $skills = $this->discovery()->discover([
            'vendor/pkg-a' => ['dirs' => [], 'includes' => [], 'skills' => []],
        ]);

        $this->assertSame([], $skills);
    }

    private function discovery(): SkillDiscovery
    {
        return new SkillDiscovery($this->rootDir, new SkillFrontmatter(), new NullLogger());
    }
}
