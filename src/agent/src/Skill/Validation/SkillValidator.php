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
use Symfony\Component\String\UnicodeString;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SkillValidator implements SkillValidatorInterface
{
    private const ALLOWED_FIELDS = ['name', 'description', 'license', 'compatibility', 'metadata', 'allowed-tools'];

    public function validate(SkillInterface $skill): SkillValidationResult
    {
        $errors = [];
        $warnings = [];

        $metadata = $skill->getMetadata();

        // 1. Validate name field
        $name = $metadata->getName();
        $unicodeName = new UnicodeString($name);

        if ($unicodeName->isEmpty()) {
            $errors[] = 'Field "name" must not be empty.';
        } elseif (!$unicodeName->match('/^[a-z0-9]+(-[a-z0-9]+)*$/')) {
            $errors[] = \sprintf('Field "name" must be kebab-case (e.g. "my-skill"), got "%s".', $name);
        } elseif ($unicodeName->length() > 64) {
            $errors[] = 'Field "name" is too long. Use a shorter name.';
        }

        // 2. Validate description field
        $description = $metadata->getDescription();
        $unicodeDesc = new UnicodeString($description);

        if ($unicodeDesc->trim()->isEmpty()) {
            $errors[] = 'Field "description" must not be empty.';
        } elseif ($unicodeDesc->length() < 20) {
            $warnings[] = \sprintf('Field "description" is short (%d chars). Consider a more descriptive text (recommended: 20+ chars).', $unicodeDesc->length());
        } elseif ($unicodeDesc->length() > 1024) {
            $errors[] = 'Field "description" is too long. Consider using a shorter description.';
        }

        // 3. Validate optional license field
        $license = $metadata->getLicense();
        if (null !== $license && !\is_string($license)) {
            $errors[] = \sprintf('Field "license" must be a string, got %s.', get_debug_type($license));
        }

        // 4. Validate optional allowed-tools field
        $allowedTools = $metadata->getAllowedTools();
        if (!\is_array($allowedTools)) {
            $errors[] = \sprintf('Field "allowed-tools" must be an array, got %s.', get_debug_type($allowedTools));
        }

        // 5. Validate optional compatibility field
        $compatibility = $metadata->getCompatibility();
        if (null !== $compatibility && !\is_string($compatibility)) {
            $errors[] = \sprintf('Field "compatibility" must be a string, got %s.', get_debug_type($compatibility));
        } elseif (null !== $compatibility && (new UnicodeString($compatibility))->length() > 500) {
            $errors[] = 'Field "compatibility" is too long. Maximum is 500 characters.';
        }

        // 6. Validate optional metadata field
        $metadataFields = $metadata->getMetadata();
        if (!\is_array($metadataFields)) {
            $errors[] = \sprintf('Field "metadata" must be a mapping, got %s.', get_debug_type($metadataFields));
        }

        // 7. Check body content
        $body = $skill->getBody();
        if ('' === trim($body)) {
            $warnings[] = 'Skill has no body content. Consider adding instructions.';
        }

        foreach (array_keys($metadata->getFrontmatter()) as $fields) {
            if (!\in_array($fields, self::ALLOWED_FIELDS, true)) {
                $warnings[] = \sprintf('Unknown frontmatter field "%s".', $fields);
            }
        }

        return new SkillValidationResult($skill, $errors, $warnings);
    }
}
