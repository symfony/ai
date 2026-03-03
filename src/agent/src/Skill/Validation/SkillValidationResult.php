<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Skill\Validation;

use Symfony\AI\Agent\Skill\SkillInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SkillValidationResult implements SkillValidationResultInterface
{
    /**
     * @param string[] $errors   Validation errors (spec violations)
     * @param string[] $warnings Validation warnings (best-practice recommendations)
     */
    public function __construct(
        private readonly SkillInterface $skill,
        private readonly array $errors = [],
        private readonly array $warnings = [],
    ) {
    }

    public function getSkill(): SkillInterface
    {
        return $this->skill;
    }

    public function getSkillName(): string
    {
        return $this->skill->getName();
    }

    public function isValid(): bool
    {
        return [] === $this->errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function hasWarnings(): bool
    {
        return [] !== $this->warnings;
    }
}
