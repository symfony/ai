<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Profiler;

use Symfony\AI\Agent\Skill\SkillInterface;
use Symfony\AI\Agent\Skill\Validation\SkillValidationResult;
use Symfony\AI\Agent\Skill\Validation\SkillValidatorInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @phpstan-type SkillValidatorData array{
 *     skill: SkillInterface,
 *     result: SkillValidationResult,
 *     validated_at: \DateTimeImmutable,
 * }
 */
final class TraceableSkillValidator implements SkillValidatorInterface, ResetInterface
{
    /**
     * @var SkillValidatorData[]
     */
    public array $calls = [];

    public function __construct(
        private readonly SkillValidatorInterface $skillValidator,
        private readonly ClockInterface $clock = new MonotonicClock(),
    ) {
    }

    public function validate(SkillInterface $skill): SkillValidationResult
    {
        $result = $this->skillValidator->validate($skill);

        $this->calls[] = [
            'skill' => $skill,
            'result' => $result,
            'validated_at' => $this->clock->now(),
        ];

        return $result;
    }

    public function reset(): void
    {
        $this->calls = [];
    }
}
