<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Skill;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Skill\FilesystemSkillLoader;
use Symfony\AI\Agent\Skill\Skill;
use Symfony\AI\Agent\Skill\SkillMetadata;
use Symfony\Component\Filesystem\Filesystem;

final class FilesystemSkillLoaderTest extends TestCase
{
    private string $tempDir;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->tempDir = sys_get_temp_dir().'/skill_discovery_test_'.bin2hex(random_bytes(4));
        $this->filesystem->mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->tempDir);
    }

    public function testDiscoverMetadataReturnsEmptyWhenNoDirectories()
    {
        $discovery = new FilesystemSkillLoader([]);

        $this->assertSame([], $discovery->discoverMetadata());
    }

    public function testDiscoverMetadataReturnsEmptyWhenDirectoryDoesNotExist()
    {
        $discovery = new FilesystemSkillLoader(['/non/existent/path']);

        $this->assertSame([], $discovery->discoverMetadata());
    }

    public function testDiscoverMetadataReturnsEmptyWhenNoSkillsFound()
    {
        $this->filesystem->mkdir($this->tempDir.'/not-a-skill');
        $this->filesystem->dumpFile($this->tempDir.'/not-a-skill/README.md', 'Not a skill');

        $discovery = new FilesystemSkillLoader([$this->tempDir]);

        $this->assertSame([], $discovery->discoverMetadata());
    }

    public function testDiscoverMetadataFindsSkills()
    {
        $this->createSkillDirectory('code-review', 'Reviews code changes');
        $this->createSkillDirectory('pdf-reader', 'Reads PDF documents');

        $discovery = new FilesystemSkillLoader([$this->tempDir]);
        $metadata = $discovery->discoverMetadata();

        $this->assertCount(2, $metadata);
        $this->assertArrayHasKey('code-review', $metadata);
        $this->assertArrayHasKey('pdf-reader', $metadata);
        $this->assertInstanceOf(SkillMetadata::class, $metadata['code-review']);
        $this->assertSame('Reviews code changes', $metadata['code-review']->getDescription());
    }

    public function testDiscoverMetadataSkipsInvalidSkills()
    {
        $this->createSkillDirectory('valid-skill', 'A valid skill');

        // Create an invalid skill (missing description)
        $this->filesystem->mkdir($this->tempDir.'/invalid-skill');
        $this->filesystem->dumpFile($this->tempDir.'/invalid-skill/SKILL.md', "---\nname: invalid-skill\n---\nBody.");

        $discovery = new FilesystemSkillLoader([$this->tempDir]);
        $metadata = $discovery->discoverMetadata();

        $this->assertCount(1, $metadata);
        $this->assertArrayHasKey('valid-skill', $metadata);
    }

    public function testDiscoverMetadataFromMultipleDirectories()
    {
        $dir1 = $this->tempDir.'/dir1';
        $dir2 = $this->tempDir.'/dir2';
        $this->filesystem->mkdir([$dir1, $dir2]);

        $this->createSkillDirectoryIn($dir1, 'skill-a', 'Skill A');
        $this->createSkillDirectoryIn($dir2, 'skill-b', 'Skill B');

        $discovery = new FilesystemSkillLoader([$dir1, $dir2]);
        $metadata = $discovery->discoverMetadata();

        $this->assertCount(2, $metadata);
        $this->assertArrayHasKey('skill-a', $metadata);
        $this->assertArrayHasKey('skill-b', $metadata);
    }

    public function testLoadSkillReturnsSkillWhenFound()
    {
        $this->createSkillDirectory('my-skill', 'My skill description');

        $discovery = new FilesystemSkillLoader([$this->tempDir]);
        $skill = $discovery->loadSkill('my-skill');

        $this->assertInstanceOf(Skill::class, $skill);
        $this->assertSame('my-skill', $skill->getName());
        $this->assertSame('My skill description', $skill->getDescription());
    }

    public function testLoadSkillReturnsNullWhenNotFound()
    {
        $this->createSkillDirectory('other-skill', 'Some skill');

        $discovery = new FilesystemSkillLoader([$this->tempDir]);

        $this->assertNull($discovery->loadSkill('non-existent'));
    }

    public function testLoadSkillReturnsNullFromEmptyDirectories()
    {
        $discovery = new FilesystemSkillLoader([]);

        $this->assertNull($discovery->loadSkill('anything'));
    }

    public function testLoadAllSkillsReturnsAllSkills()
    {
        $this->createSkillDirectory('skill-one', 'First skill');
        $this->createSkillDirectory('skill-two', 'Second skill');

        $discovery = new FilesystemSkillLoader([$this->tempDir]);
        $skills = $discovery->loadSkills();

        $this->assertCount(2, $skills);
        $this->assertArrayHasKey('skill-one', $skills);
        $this->assertArrayHasKey('skill-two', $skills);
        $this->assertInstanceOf(Skill::class, $skills['skill-one']);
        $this->assertInstanceOf(Skill::class, $skills['skill-two']);
    }

    public function testLoadAllSkillsReturnsEmptyWhenNone()
    {
        $discovery = new FilesystemSkillLoader([$this->tempDir]);

        $this->assertSame([], $discovery->loadSkills());
    }

    public function testLoadAllSkillsSkipsInvalidOnes()
    {
        $this->createSkillDirectory('good-skill', 'A good skill');

        // Invalid: file instead of directory
        $this->filesystem->dumpFile($this->tempDir.'/file-not-dir/SKILL.md', 'invalid');

        $discovery = new FilesystemSkillLoader([$this->tempDir]);
        $skills = $discovery->loadSkills();

        $this->assertCount(1, $skills);
        $this->assertArrayHasKey('good-skill', $skills);
    }

    public function testSkipsFilesInBaseDirectory()
    {
        // A file directly in the base dir should be ignored
        $this->filesystem->dumpFile($this->tempDir.'/stray-file.txt', 'not a skill');
        $this->createSkillDirectory('real-skill', 'A real skill');

        $discovery = new FilesystemSkillLoader([$this->tempDir]);
        $metadata = $discovery->discoverMetadata();

        $this->assertCount(1, $metadata);
        $this->assertArrayHasKey('real-skill', $metadata);
    }

    private function createSkillDirectory(string $name, string $description): void
    {
        $this->createSkillDirectoryIn($this->tempDir, $name, $description);
    }

    private function createSkillDirectoryIn(string $baseDir, string $name, string $description): void
    {
        $skillDir = $baseDir.'/'.$name;

        $this->filesystem->mkdir($skillDir);
        $this->filesystem->dumpFile(
            $skillDir.'/SKILL.md',
            \sprintf("---\nname: %s\ndescription: %s\n---\nInstructions for %s.", $name, $description, $name),
        );
    }
}
