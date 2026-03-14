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
use Symfony\AI\Agent\Skill\SkillMetadata;
use Symfony\AI\Agent\Skill\Validation\SkillValidationResult;
use Symfony\AI\Agent\Skill\Validation\SkillValidatorInterface;
use Symfony\AI\AiBundle\Profiler\TraceableSkillValidator;
use Symfony\Component\Clock\MockClock;

final class TraceableSkillValidatorTest extends TestCase
{
    public function testValidateTracksCall()
    {
        $skill = new Skill('Body.', new SkillMetadata('my-skill', 'A test skill'));
        $validationResult = new SkillValidationResult($skill);
        $clock = new MockClock();

        $innerValidator = $this->createMock(SkillValidatorInterface::class);
        $innerValidator->method('validate')
            ->with($skill)
            ->willReturn($validationResult);

        $validator = new TraceableSkillValidator($innerValidator, $clock);
        $result = $validator->validate($skill);

        $this->assertSame($validationResult, $result);
        $this->assertCount(1, $validator->calls);
        $this->assertSame($skill, $validator->calls[0]['skill']);
        $this->assertSame($validationResult, $validator->calls[0]['result']);
        $this->assertEquals($clock->now(), $validator->calls[0]['validated_at']);
    }

    public function testResetClearsCalls()
    {
        $skill = new Skill('Body.', new SkillMetadata('my-skill', 'A test skill'));
        $validationResult = new SkillValidationResult($skill);
        $clock = new MockClock();

        $innerValidator = $this->createMock(SkillValidatorInterface::class);
        $innerValidator->method('validate')
            ->willReturn($validationResult);

        $validator = new TraceableSkillValidator($innerValidator, $clock);
        $validator->validate($skill);

        $this->assertCount(1, $validator->calls);

        $validator->reset();

        $this->assertCount(0, $validator->calls);
    }
}
