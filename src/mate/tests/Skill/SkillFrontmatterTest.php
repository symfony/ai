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
use Symfony\AI\Mate\Skill\SkillFrontmatter;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SkillFrontmatterTest extends TestCase
{
    public function testParsesNameAndDescription()
    {
        $frontmatter = new SkillFrontmatter();

        $result = $frontmatter->parse(<<<'MD'
            ---
            name: system-information
            description: Provides system information.
            ---

            # Body
            MD);

        $this->assertSame([
            'name' => 'system-information',
            'description' => 'Provides system information.',
        ], $result);
    }

    public function testParsesFoldedBlockScalar()
    {
        $frontmatter = new SkillFrontmatter();

        $result = $frontmatter->parse(<<<'MD'
            ---
            name: system-information
            description: >-
              Inspect the runtime context of the application
              across several wrapped lines.
            ---

            # Body
            MD);

        $this->assertSame([
            'name' => 'system-information',
            'description' => 'Inspect the runtime context of the application across several wrapped lines.',
        ], $result);
    }

    public function testParsesLiteralBlockScalar()
    {
        $frontmatter = new SkillFrontmatter();

        $result = $frontmatter->parse(<<<'MD'
            ---
            description: |
              first line
              second line
            ---
            MD);

        $this->assertSame(['description' => "first line\nsecond line"], $result);
    }

    public function testBlockScalarStopsAtTheNextKey()
    {
        $frontmatter = new SkillFrontmatter();

        $result = $frontmatter->parse(<<<'MD'
            ---
            description: >-
              wrapped value
            name: system-information
            ---
            MD);

        $this->assertSame([
            'description' => 'wrapped value',
            'name' => 'system-information',
        ], $result);
    }

    public function testParsesTheBuiltInSkill()
    {
        $content = file_get_contents(__DIR__.'/../../skills/system-information/SKILL.md');
        $this->assertIsString($content);

        $result = (new SkillFrontmatter())->parse($content);

        $this->assertIsArray($result);
        $this->assertSame('system-information', $result['name']);
        $this->assertStringStartsWith('Inspect the runtime', $result['description']);
    }

    public function testRewriteNameKeepsRegexMetacharactersLiteral()
    {
        $frontmatter = new SkillFrontmatter();

        $rewritten = $frontmatter->rewriteName(<<<'MD'
            ---
            name: original
            ---
            MD, 'mate-$1\weird');

        $this->assertStringContainsString('name: mate-$1\weird', $rewritten);
        $this->assertStringNotContainsString('name: original', $rewritten);
    }

    public function testStripsQuotesAroundValues()
    {
        $frontmatter = new SkillFrontmatter();

        $result = $frontmatter->parse(<<<'MD'
            ---
            name: "system-information"
            description: 'A: quoted value'
            ---
            body
            MD);

        $this->assertNotNull($result);
        $this->assertSame('system-information', $result['name']);
        $this->assertSame('A: quoted value', $result['description']);
    }

    public function testReturnsNullWithoutFrontmatter()
    {
        $frontmatter = new SkillFrontmatter();

        $this->assertNull($frontmatter->parse("# Just a heading\n\nNo front matter here."));
    }

    public function testRewriteNameReplacesOnlyFrontmatterName()
    {
        $frontmatter = new SkillFrontmatter();

        $content = <<<'MD'
            ---
            name: system-information
            description: The name key should stay in the body.
            ---

            The name: original stays untouched in the body.
            MD;

        $rewritten = $frontmatter->rewriteName($content, 'mate-system-information');

        $this->assertStringContainsString('name: mate-system-information', $rewritten);
        $this->assertStringContainsString('The name: original stays untouched in the body.', $rewritten);
        $this->assertStringNotContainsString('name: system-information', $rewritten);

        $parsed = $frontmatter->parse($rewritten);
        $this->assertNotNull($parsed);
        $this->assertSame('mate-system-information', $parsed['name']);
    }

    public function testRewriteNameLeavesContentUnchangedWithoutFrontmatter()
    {
        $frontmatter = new SkillFrontmatter();

        $content = "# No front matter\n";

        $this->assertSame($content, $frontmatter->rewriteName($content, 'mate-x'));
    }
}
