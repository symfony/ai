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
 * Validates an Agent Skill directory against the specification.
 *
 * Checks performed (mirroring the reference validator):
 *  - SKILL.md existence
 *  - YAML frontmatter structure
 *  - Required fields: name, description
 *  - Field types and formats
 *  - Name must be kebab-case
 *  - Description should be meaningful (warning if < 20 chars)
 *  - Optional fields type-checking: license, allowed-tools, compatibility, metadata
 *  - Optional directories: scripts/, references/, assets/
 *  - Unknown top-level files/directories (warning)
 *
 * @see https://agentskills.io/specification
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface SkillValidatorInterface
{
    public function validate(SkillInterface $skill): SkillValidationResult;
}
