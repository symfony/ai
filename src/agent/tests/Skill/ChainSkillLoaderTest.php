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
use Symfony\AI\Agent\Skill\ChainSkillLoader;
use Symfony\AI\Agent\Skill\Skill;
use Symfony\AI\Agent\Skill\SkillLoaderInterface;
use Symfony\AI\Agent\Skill\SkillMetadata;

final class ChainSkillLoaderTest extends TestCase
{
    public function testLoadSkillReturnsFirstMatch()
    {
        $skill = new Skill('Body.', new SkillMetadata('my-skill', 'A skill'));

        $loader1 = $this->createMock(SkillLoaderInterface::class);
        $loader1->method('loadSkill')->with('my-skill')->willReturn(null);

        $loader2 = $this->createMock(SkillLoaderInterface::class);
        $loader2->method('loadSkill')->with('my-skill')->willReturn($skill);

        $chain = new ChainSkillLoader([$loader1, $loader2]);

        $this->assertSame($skill, $chain->loadSkill('my-skill'));
    }

    public function testLoadSkillReturnsNullWhenNoneFound()
    {
        $loader1 = $this->createMock(SkillLoaderInterface::class);
        $loader1->method('loadSkill')->willReturn(null);

        $loader2 = $this->createMock(SkillLoaderInterface::class);
        $loader2->method('loadSkill')->willReturn(null);

        $chain = new ChainSkillLoader([$loader1, $loader2]);

        $this->assertNull($chain->loadSkill('missing-skill'));
    }

    public function testLoadSkillStopsAtFirstMatch()
    {
        $skill = new Skill('Body.', new SkillMetadata('my-skill', 'A skill'));

        $loader1 = $this->createMock(SkillLoaderInterface::class);
        $loader1->method('loadSkill')->willReturn($skill);

        $loader2 = $this->createMock(SkillLoaderInterface::class);
        $loader2->expects($this->never())->method('loadSkill');

        $chain = new ChainSkillLoader([$loader1, $loader2]);

        $this->assertSame($skill, $chain->loadSkill('my-skill'));
    }

    public function testLoadSkillsAggregatesFromAllLoaders()
    {
        $skill1 = new Skill('Body 1.', new SkillMetadata('skill-one', 'First skill'));
        $skill2 = new Skill('Body 2.', new SkillMetadata('skill-two', 'Second skill'));

        $loader1 = $this->createMock(SkillLoaderInterface::class);
        $loader1->method('loadSkills')->willReturn(['skill-one' => $skill1]);

        $loader2 = $this->createMock(SkillLoaderInterface::class);
        $loader2->method('loadSkills')->willReturn(['skill-two' => $skill2]);

        $chain = new ChainSkillLoader([$loader1, $loader2]);
        $skills = $chain->loadSkills();

        $this->assertCount(2, $skills);
        $this->assertArrayHasKey('skill-one', $skills);
        $this->assertArrayHasKey('skill-two', $skills);
    }

    public function testLoadSkillsFirstLoaderWinsOnConflict()
    {
        $skill1 = new Skill('From loader 1.', new SkillMetadata('my-skill', 'First version'));
        $skill2 = new Skill('From loader 2.', new SkillMetadata('my-skill', 'Second version'));

        $loader1 = $this->createMock(SkillLoaderInterface::class);
        $loader1->method('loadSkills')->willReturn(['my-skill' => $skill1]);

        $loader2 = $this->createMock(SkillLoaderInterface::class);
        $loader2->method('loadSkills')->willReturn(['my-skill' => $skill2]);

        $chain = new ChainSkillLoader([$loader1, $loader2]);
        $skills = $chain->loadSkills();

        $this->assertCount(1, $skills);
        $this->assertSame('From loader 1.', $skills['my-skill']->getBody());
    }

    public function testDiscoverMetadataAggregatesFromAllLoaders()
    {
        $meta1 = new SkillMetadata('skill-one', 'First');
        $meta2 = new SkillMetadata('skill-two', 'Second');

        $loader1 = $this->createMock(SkillLoaderInterface::class);
        $loader1->method('discoverMetadata')->willReturn(['skill-one' => $meta1]);

        $loader2 = $this->createMock(SkillLoaderInterface::class);
        $loader2->method('discoverMetadata')->willReturn(['skill-two' => $meta2]);

        $chain = new ChainSkillLoader([$loader1, $loader2]);
        $metadata = $chain->discoverMetadata();

        $this->assertCount(2, $metadata);
        $this->assertArrayHasKey('skill-one', $metadata);
        $this->assertArrayHasKey('skill-two', $metadata);
    }

    public function testDiscoverMetadataFirstLoaderWinsOnConflict()
    {
        $meta1 = new SkillMetadata('my-skill', 'First version');
        $meta2 = new SkillMetadata('my-skill', 'Second version');

        $loader1 = $this->createMock(SkillLoaderInterface::class);
        $loader1->method('discoverMetadata')->willReturn(['my-skill' => $meta1]);

        $loader2 = $this->createMock(SkillLoaderInterface::class);
        $loader2->method('discoverMetadata')->willReturn(['my-skill' => $meta2]);

        $chain = new ChainSkillLoader([$loader1, $loader2]);
        $metadata = $chain->discoverMetadata();

        $this->assertCount(1, $metadata);
        $this->assertSame('First version', $metadata['my-skill']->getDescription());
    }
}
