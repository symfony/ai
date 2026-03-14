<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Skill\Validation;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Skill\Skill;
use Symfony\AI\Agent\Skill\SkillMetadata;
use Symfony\AI\Agent\Skill\SkillParser;
use Symfony\AI\Agent\Skill\Validation\SkillValidator;
use Symfony\Component\Filesystem\Filesystem;

final class SkillValidatorTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/skill_validator_test_'.bin2hex(random_bytes(4));

        (new Filesystem())->mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    public function testValidateMinimalSkill()
    {
        $this->createSkillFile("---\nname: my-skill\ndescription: A useful skill for testing purposes\n---\nDo something useful.");

        $skill = (new SkillParser())->parse($this->tempDir);

        $result = (new SkillValidator())->validate($skill);

        $this->assertTrue($result->isValid());
        $this->assertSame([], $result->getErrors());
    }

    public function testValidateShortDescriptionWarning()
    {
        $this->createSkillFile("---\nname: short-desc\ndescription: Short\n---\nBody content here.");

        $skill = (new SkillParser())->parse($this->tempDir);

        $result = (new SkillValidator())->validate($skill);

        $this->assertTrue($result->isValid());
        $this->assertTrue($result->hasWarnings());
        $this->assertStringContainsString('short', $result->getWarnings()[0]);
    }

    public function testValidateUnknownFrontmatterFieldWarning()
    {
        $this->createSkillFile("---\nname: my-skill\ndescription: A properly described skill here\nunknown-field: value\n---\nBody.");

        $skill = (new SkillParser())->parse($this->tempDir);

        $result = (new SkillValidator())->validate($skill);

        $this->assertTrue($result->isValid());
        $this->assertTrue($result->hasWarnings());
        $this->assertStringContainsString('Unknown frontmatter field "unknown-field"', $result->getWarnings()[0]);
    }

    public function testValidateEmptyBodyWarning()
    {
        $this->createSkillFile("---\nname: empty-body\ndescription: Skill with no body content at all\n---\n");

        $skill = (new SkillParser())->parse($this->tempDir);

        $result = (new SkillValidator())->validate($skill);

        $this->assertTrue($result->isValid());
        $this->assertTrue($result->hasWarnings());

        $bodyWarning = array_filter($result->getWarnings(), static fn (string $w): bool => str_contains($w, 'no body content'));

        $this->assertNotEmpty($bodyWarning);
    }

    public function testValidateFileSystemAllOptionalFields()
    {
        $content = <<<'MD'
            ---
            name: full-skill
            description: A complete skill with all optional fields populated
            license: MIT
            allowed-tools: Read Write Bash
            compatibility: claude >=3.5
            metadata:
              author: Symfony
              version: 1.0.0
            ---
            ## Instructions

            Do great things.
            MD;

        $this->createSkillFile($content);

        $skill = (new SkillParser())->parse($this->tempDir);

        $result = (new SkillValidator())->validate($skill);

        $this->assertTrue($result->isValid());
        $this->assertSame([], $result->getErrors());

        $licenseWarnings = array_filter($result->getWarnings(), static fn (string $w): bool => str_contains($w, 'license'));
        $this->assertSame([], $licenseWarnings);
    }

    public function testValidateLicenseFieldIsRecognized()
    {
        $this->createSkillFile("---\nname: license-skill\ndescription: A properly described skill for testing purposes\nlicense: MIT\n---\nBody.");

        $skill = (new SkillParser())->parse($this->tempDir);

        $result = (new SkillValidator())->validate($skill);

        $this->assertTrue($result->isValid());

        $licenseWarnings = array_filter($result->getWarnings(), static fn (string $w): bool => str_contains($w, 'license'));
        $this->assertSame([], $licenseWarnings);
    }

    public function testValidateDescriptionTooLong()
    {
        $longDescription = str_repeat('a', 1025);
        $metadata = new SkillMetadata('my-skill', $longDescription);
        $skill = new Skill('Body content.', $metadata);

        $result = (new SkillValidator())->validate($skill);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('too long', $result->getErrors()[0]);
    }

    public function testValidateDescriptionAtExactLimit()
    {
        $exactDescription = str_repeat('a', 1024);
        $metadata = new SkillMetadata('my-skill', $exactDescription);
        $skill = new Skill('Body content.', $metadata);

        $result = (new SkillValidator())->validate($skill);

        $this->assertTrue($result->isValid());
    }

    public function testValidateCompatibilityTooLong()
    {
        $longCompatibility = str_repeat('a', 501);
        $metadata = new SkillMetadata('my-skill', 'A properly described skill for testing purposes', compatibility: $longCompatibility);
        $skill = new Skill('Body content.', $metadata);

        $result = (new SkillValidator())->validate($skill);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('compatibility', $result->getErrors()[0]);
    }

    public function testValidateSkillInterfaceWithShortDescription()
    {
        $metadata = new SkillMetadata('my-skill', 'Short');
        $skill = new Skill('Body content.', $metadata);

        $result = (new SkillValidator())->validate($skill);

        $this->assertTrue($result->isValid());
        $this->assertTrue($result->hasWarnings());
        $this->assertStringContainsString('short', $result->getWarnings()[0]);
    }

    public function testValidateSkillInterfaceWithEmptyBody()
    {
        $metadata = new SkillMetadata('my-skill', 'A properly described skill for testing purposes');
        $skill = new Skill('', $metadata);

        $result = (new SkillValidator())->validate($skill);

        $this->assertTrue($result->isValid());
        $this->assertTrue($result->hasWarnings());
        $this->assertStringContainsString('no body content', $result->getWarnings()[0]);
    }

    private function createSkillFile(string $content): void
    {
        $lines = explode("\n", $content);
        $minIndent = \PHP_INT_MAX;

        foreach ($lines as $line) {
            if ('' !== trim($line)) {
                $minIndent = min($minIndent, \strlen($line) - \strlen(ltrim($line)));
            }
        }

        if ($minIndent > 0 && $minIndent < \PHP_INT_MAX) {
            $lines = array_map(static fn (string $l): string => \strlen($l) >= $minIndent ? substr($l, $minIndent) : $l, $lines);
        }

        (new Filesystem())->dumpFile($this->tempDir.'/SKILL.md', implode("\n", $lines));
    }
}
