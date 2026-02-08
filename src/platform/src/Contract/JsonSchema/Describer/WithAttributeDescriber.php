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

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\With;
use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use Symfony\AI\Platform\Contract\JsonSchema\Subject\PropertySubject;

/**
 * @phpstan-import-type JsonSchema from Factory
 */
final class WithAttributeDescriber implements PropertyDescriberInterface
{
    public function describeProperty(PropertySubject $subject, ?array &$schema): void
    {
        foreach ($subject->getAttributes(With::class) as $attribute) {
            $schema = array_replace_recursive($schema ?? [], array_filter((array) $attribute, static fn ($value) => null !== $value));
        }
    }
}
