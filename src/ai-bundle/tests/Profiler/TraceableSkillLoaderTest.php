<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Tests\Profiler;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Skill\Skill;
use Symfony\AI\Agent\Skill\SkillLoaderInterface;
use Symfony\AI\Agent\Skill\SkillMetadata;
use Symfony\AI\AiBundle\Profiler\TraceableSkillLoader;
use Symfony\Component\Clock\MockClock;

final class TraceableSkillLoaderTest extends TestCase
{
    public function testLoadSkillTracksCall()
    {
        $skill = new Skill('Body.', new SkillMetadata('my-skill', 'A skill'));
        $clock = new MockClock();

        $innerLoader = $this->createMock(SkillLoaderInterface::class);
        $innerLoader->method('loadSkill')
            ->with('my-skill')
            ->willReturn($skill);

        $loader = new TraceableSkillLoader($innerLoader, $clock);
        $result = $loader->loadSkill('my-skill');

        $this->assertSame($skill, $result);
        $this->assertCount(1, $loader->calls);
        $this->assertSame('loadSkill', $loader->calls[0]['method']);
        $this->assertSame('my-skill', $loader->calls[0]['skill']);
        $this->assertEquals($clock->now(), $loader->calls[0]['loaded_at']);
    }

    public function testLoadSkillsTracksCall()
    {
        $skill = new Skill('Body.', new SkillMetadata('my-skill', 'A skill'));
        $clock = new MockClock();

        $innerLoader = $this->createMock(SkillLoaderInterface::class);
        $innerLoader->method('loadSkills')
            ->willReturn(['my-skill' => $skill]);

        $loader = new TraceableSkillLoader($innerLoader, $clock);
        $result = $loader->loadSkills();

        $this->assertSame(['my-skill' => $skill], $result);
        $this->assertCount(1, $loader->calls);
        $this->assertSame('loadSkills', $loader->calls[0]['method']);
        $this->assertSame(['my-skill'], $loader->calls[0]['skills']);
    }

    public function testDiscoverMetadataTracksCall()
    {
        $metadata = new SkillMetadata('my-skill', 'A skill');
        $clock = new MockClock();

        $innerLoader = $this->createMock(SkillLoaderInterface::class);
        $innerLoader->method('discoverMetadata')
            ->willReturn(['my-skill' => $metadata]);

        $loader = new TraceableSkillLoader($innerLoader, $clock);
        $result = $loader->discoverMetadata();

        $this->assertSame(['my-skill' => $metadata], $result);
        $this->assertCount(1, $loader->calls);
        $this->assertSame('discoverMetadata', $loader->calls[0]['method']);
        $this->assertSame(['my-skill' => $metadata], $loader->calls[0]['metadata']);
    }

    public function testResetClearsCalls()
    {
        $clock = new MockClock();

        $innerLoader = $this->createMock(SkillLoaderInterface::class);
        $innerLoader->method('loadSkill')->willReturn(null);

        $loader = new TraceableSkillLoader($innerLoader, $clock);
        $loader->loadSkill('test');

        $this->assertCount(1, $loader->calls);

        $loader->reset();

        $this->assertCount(0, $loader->calls);
    }
}
