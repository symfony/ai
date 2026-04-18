<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\JsonSchema\Describer;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;
use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use Symfony\AI\Platform\Contract\JsonSchema\Subject\PropertySubject;
use Symfony\AI\Platform\Exception\IOException;

/**
 * @phpstan-import-type JsonSchema from Factory
 */
final class SchemaAttributeDescriber implements PropertyDescriberInterface
{
    public function describeProperty(PropertySubject $subject, ?array &$schema): void
    {
        foreach ($subject->getAttributes(Schema::class) as $attribute) {
            if ($attribute->ref) {
                try {
                    $attributeSchema = json_decode(file_get_contents($attribute->ref), true, flags: \JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    throw new IOException(\sprintf('Failed to load the schema from "%s"', $attribute->ref), 0, $e);
                }
            } else {
                $attributeSchema = array_filter((array) $attribute, static fn ($value) => null !== $value);
            }

            $schema = array_replace_recursive($schema ?? [], $attributeSchema);
        }
    }
}
