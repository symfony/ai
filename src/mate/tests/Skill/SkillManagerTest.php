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
use Symfony\AI\Mate\Exception\AmbiguousSkillException;
use Symfony\AI\Mate\Exception\RuntimeException;
use Symfony\AI\Mate\Exception\SkillNotFoundException;
use Symfony\AI\Mate\Skill\SkillStateRepository;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SkillManagerTest extends TestCase
{
    use SkillFixtureTrait;
    use SkillServicesTrait;

    private string $rootDir;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir().'/mate-skill-manager-'.uniqid();
        mkdir($this->rootDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->rootDir);
    }

    public function testResolvesByOriginalName()
    {
        $this->installFixture();

        $this->assertSame(
            ['package' => 'vendor/pkg-a', 'name' => 'system-information'],
            $this->createManager($this->rootDir)->resolve('system-information'),
        );
    }

    public function testResolvesByInstalledName()
    {
        $this->installFixture();

        $this->assertSame(
            ['package' => 'vendor/pkg-a', 'name' => 'system-information'],
            $this->createManager($this->rootDir)->resolve('mate-system-information'),
        );
    }

    public function testResolveThrowsOnUnknownName()
    {
        $this->installFixture();

        $this->expectException(SkillNotFoundException::class);
        $this->createManager($this->rootDir)->resolve('nope');
    }

    public function testResolveThrowsWhenTwoPackagesOwnTheName()
    {
        (new SkillStateRepository($this->rootDir))->write([
            'vendor/pkg-a' => ['enabled' => true, 'skills' => ['shared' => ['enabled' => true, 'mode' => 'managed']]],
            'vendor/pkg-b' => ['enabled' => true, 'skills' => ['shared' => ['enabled' => true, 'mode' => 'managed']]],
        ]);

        $this->expectException(AmbiguousSkillException::class);
        $this->createManager($this->rootDir)->resolve('shared');
    }

    public function testCreateOverrideCopyKeepsTheOriginalFrontmatterName()
    {
        $this->installFixture();

        $path = $this->createManager($this->rootDir)->createOverrideCopy('vendor/pkg-a', 'system-information', false);

        $this->assertSame('mate/skills/system-information', $path);
        $content = file_get_contents($this->rootDir.'/'.$path.'/SKILL.md');
        $this->assertIsString($content);
        $this->assertStringContainsString('name: system-information', $content);
    }

    public function testCreateOverrideCopyRefusesToReplaceWithoutForce()
    {
        $this->installFixture();
        $manager = $this->createManager($this->rootDir);
        $manager->createOverrideCopy('vendor/pkg-a', 'system-information', false);

        $this->expectException(RuntimeException::class);
        $manager->createOverrideCopy('vendor/pkg-a', 'system-information', false);
    }

    public function testCreateOverrideCopyFailsWhenThePackageNoLongerShipsTheSkill()
    {
        $this->installFixture();
        $this->removeDirectory($this->rootDir.'/vendor/vendor/pkg-a');

        $this->expectException(RuntimeException::class);
        $this->createManager($this->rootDir)->createOverrideCopy('vendor/pkg-a', 'system-information', false);
    }

    public function testRemoveOverrideCopyReportsWhetherAnythingWasDeleted()
    {
        $this->installFixture();
        $manager = $this->createManager($this->rootDir);

        $this->assertFalse($manager->removeOverrideCopy('system-information'));

        $manager->createOverrideCopy('vendor/pkg-a', 'system-information', false);
        $this->assertTrue($manager->hasOverrideCopy('system-information'));
        $this->assertTrue($manager->removeOverrideCopy('system-information'));
        $this->assertFalse($manager->hasOverrideCopy('system-information'));
    }

    private function installFixture(): void
    {
        $this->createInstalledPackage($this->rootDir);
        $this->createSkill($this->rootDir.'/vendor/vendor/pkg-a/skills', 'system-information', 'System info.');
        $this->createManager($this->rootDir)->reinstall();
    }
}
