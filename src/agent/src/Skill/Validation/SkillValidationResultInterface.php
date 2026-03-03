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
interface SkillValidationResultInterface
{
    public function getSkill(): SkillInterface;

    public function isValid(): bool;

    /**
     * @return string[]
     */
    public function getErrors(): array;

    /**
     * @return string[]
     */
    public function getWarnings(): array;

    public function hasWarnings(): bool;
}
