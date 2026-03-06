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
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Skill\Skill;
use Symfony\AI\Agent\Skill\SkillMetadata;
use Symfony\AI\Agent\Skill\SkillParser;
use Symfony\Component\Filesystem\Filesystem;

final class SkillParserTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/skill_parser_test_'.bin2hex(random_bytes(4));

        (new Filesystem())->mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    public function testEmptyNameField()
    {
        $this->createSkillFile("---\nname: \"\"\ndescription: A skill with empty name\n---\nBody.");

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Missing or invalid required field "name" in "%s/SKILL.md".', $this->tempDir));
        $this->expectExceptionCode(0);
        (new SkillParser())->parse($this->tempDir);
    }

    public function testInvalidKebabCaseName()
    {
        $this->createSkillFile("---\nname: My_Skill\ndescription: A skill with bad name format\n---\nBody.");

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Skill name "My_Skill" must be non-empty kebab-case (e.g. "my-skill")');
        $this->expectExceptionCode(0);
        (new SkillParser())->parse($this->tempDir);
    }

    public function testParseMinimalSkill()
    {
        $this->createSkillFile(<<<'MD'
            ---
            name: my-skill
            description: A simple skill
            ---
            Do something useful.
            MD);

        $skill = (new SkillParser())->parse($this->tempDir);

        $this->assertInstanceOf(Skill::class, $skill);
        $this->assertSame('my-skill', $skill->getName());
        $this->assertSame('A simple skill', $skill->getDescription());
        $this->assertSame('Do something useful.', $skill->getBody());
    }

    public function testParseSkillWithAllFrontmatterFields()
    {
        $this->createSkillFile(<<<'MD'
            ---
            name: pdf-processing
            description: Processes PDF documents and extracts content
            license: MIT
            allowed-tools: Read Write Bash
            compatibility: claude >=3.5
            metadata:
              author: Symfony
              version: 1.0.0
            ---
            ## Instructions

            Extract text from PDF files.
            MD);

        $skill = (new SkillParser())->parse($this->tempDir);

        $this->assertSame('pdf-processing', $skill->getName());
        $this->assertSame('Processes PDF documents and extracts content', $skill->getDescription());

        $metadata = $skill->getMetadata();
        $this->assertSame('MIT', $metadata->getLicense());
        $this->assertSame(['Read', 'Write', 'Bash'], $metadata->getAllowedTools());
        $this->assertSame('claude >=3.5', $metadata->getCompatibility());
        $this->assertSame('Symfony', $metadata->getAuthor());
        $this->assertSame('1.0.0', $metadata->getVersion());
        $this->assertStringContainsString('Extract text from PDF files.', $skill->getBody());
    }

    public function testParseSkillWithEmptyBody()
    {
        $this->createSkillFile(<<<'MD'
            ---
            name: empty-body
            description: Skill with no body
            ---
            MD);

        $skill = (new SkillParser())->parse($this->tempDir);

        $this->assertSame('empty-body', $skill->getName());
        $this->assertSame('', $skill->getBody());
    }

    public function testParseSkillWithMultilineBody()
    {
        $this->createSkillFile(<<<'MD'
            ---
            name: code-review
            description: Reviews code changes
            ---
            ## Step 1

            Analyze the code.

            ## Step 2

            Provide feedback.
            MD);

        $skill = (new SkillParser())->parse($this->tempDir);

        $this->assertStringContainsString('## Step 1', $skill->getBody());
        $this->assertStringContainsString('## Step 2', $skill->getBody());
        $this->assertStringContainsString('Provide feedback.', $skill->getBody());
    }

    public function testParseThrowsWhenSkillMdIsMissing()
    {
        $emptyDir = $this->tempDir.'/empty';
        (new Filesystem())->mkdir($emptyDir);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SKILL.md not found');

        (new SkillParser())->parse($emptyDir);
    }

    public function testParseThrowsWhenNoFrontmatter()
    {
        $this->createSkillFile('Just some markdown without frontmatter.');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must start with YAML frontmatter');

        (new SkillParser())->parse($this->tempDir);
    }

    public function testParseThrowsWhenFrontmatterNotClosed()
    {
        $this->createSkillFile(<<<'MD'
            ---
            name: broken
            description: Missing closing delimiter
            MD);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to parse YAML frontmatter');

        (new SkillParser())->parse($this->tempDir);
    }

    public function testParseThrowsWhenMissingName()
    {
        $this->createSkillFile(<<<'MD'
            ---
            description: A skill without a name
            ---
            Body content.
            MD);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing or invalid required field "name"');

        (new SkillParser())->parse($this->tempDir);
    }

    public function testParseThrowsWhenMissingDescription()
    {
        $this->createSkillFile(<<<'MD'
            ---
            name: no-desc
            ---
            Body content.
            MD);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing or invalid required field "description"');

        (new SkillParser())->parse($this->tempDir);
    }

    public function testParseMetadataOnlyReturnsSkillMetadata()
    {
        $this->createSkillFile(<<<'MD'
            ---
            name: lightweight
            description: Only metadata is parsed
            license: Apache-2.0
            ---
            This body should not matter for metadata-only parsing.
            MD);

        $metadata = (new SkillParser())->parseMetadataOnly($this->tempDir);

        $this->assertInstanceOf(SkillMetadata::class, $metadata);
        $this->assertSame('lightweight', $metadata->getName());
        $this->assertSame('Only metadata is parsed', $metadata->getDescription());
        $this->assertSame('Apache-2.0', $metadata->getLicense());
    }

    public function testParseMetadataOnlyThrowsWhenMissing()
    {
        $emptyDir = $this->tempDir.'/empty';
        (new Filesystem())->mkdir($emptyDir);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SKILL.md not found');

        (new SkillParser())->parseMetadataOnly($emptyDir);
    }

    public function testParseSkillWithQuotedValues()
    {
        $this->createSkillFile(<<<'MD'
            ---
            name: quoted-values
            description: "A skill with quoted values"
            license: "MIT"
            ---
            Body.
            MD);

        $skill = (new SkillParser())->parse($this->tempDir);

        $this->assertSame('quoted-values', $skill->getName());
        $this->assertSame('A skill with quoted values', $skill->getDescription());
        $this->assertSame('MIT', $skill->getMetadata()->getLicense());
    }

    public function testParseSkillWithComments()
    {
        $this->createSkillFile(<<<'MD'
            ---
            # This is a comment
            name: commented
            description: Skill with YAML comments
            ---
            Body.
            MD);

        $skill = (new SkillParser())->parse($this->tempDir);

        $this->assertSame('commented', $skill->getName());
    }

    public function testParseSkillWithAllowedToolsSingleTool()
    {
        $this->createSkillFile(<<<'MD'
            ---
            name: single-tool
            description: Skill with one tool
            allowed-tools: Read
            ---
            Body.
            MD);

        $skill = (new SkillParser())->parse($this->tempDir);

        $this->assertSame(['Read'], $skill->getMetadata()->getAllowedTools());
    }

    public function testParseFromContentReturnsSkill()
    {
        $content = "---\nname: remote-skill\ndescription: A skill parsed from content\n---\nRemote instructions.";

        $skill = (new SkillParser())->parseFromContent($content, 'github://owner/repo/remote-skill');

        $this->assertSame('remote-skill', $skill->getName());
        $this->assertSame('A skill parsed from content', $skill->getDescription());
        $this->assertSame('Remote instructions.', $skill->getBody());
    }

    public function testParseFromContentWithCustomLoaders()
    {
        $content = "---\nname: remote-skill\ndescription: A skill with remote resources\n---\nBody.";

        $skill = (new SkillParser())->parseFromContent(
            $content,
            'github://owner/repo/remote-skill',
            static fn (string $script): string => '/tmp/scripts/'.$script,
            static fn (string $ref): string => 'Reference: '.$ref,
            static fn (string $asset): string => 'Asset: '.$asset,
        );

        $this->assertSame('/tmp/scripts/setup.sh', $skill->loadScript('setup.sh'));
        $this->assertSame('Reference: guide.md', $skill->loadReference('guide.md'));
        $this->assertSame('Asset: logo.png', $skill->loadAsset('logo.png'));
    }

    public function testParseMetadataFromContentReturnsMetadata()
    {
        $content = "---\nname: remote-skill\ndescription: Metadata only\nlicense: MIT\n---\nBody is ignored for metadata.";

        $metadata = (new SkillParser())->parseMetadataFromContent($content, 'github://owner/repo/remote-skill');

        $this->assertSame('remote-skill', $metadata->getName());
        $this->assertSame('Metadata only', $metadata->getDescription());
        $this->assertSame('MIT', $metadata->getLicense());
    }

    public function testLoadReferenceBuildsCorrectPath()
    {
        $this->createSkillFile(<<<'MD'
            ---
            name: ref-skill
            description: A skill with references
            ---
            Body.
            MD);

        (new Filesystem())->mkdir($this->tempDir.'/references');
        (new Filesystem())->dumpFile($this->tempDir.'/references/guide.md', 'Reference content');

        $skill = (new SkillParser())->parse($this->tempDir);

        $this->assertSame('Reference content', $skill->loadReference('guide.md'));
    }

    public function testLoadAssetBuildsCorrectPath()
    {
        $this->createSkillFile(<<<'MD'
            ---
            name: asset-skill
            description: A skill with assets
            ---
            Body.
            MD);

        (new Filesystem())->mkdir($this->tempDir.'/assets');
        (new Filesystem())->dumpFile($this->tempDir.'/assets/template.txt', 'Asset content');

        $skill = (new SkillParser())->parse($this->tempDir);

        $this->assertSame('Asset content', $skill->loadAsset('template.txt'));
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
