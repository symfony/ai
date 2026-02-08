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

final class WithAttributeDescriber implements DescriberInterface
{
    public function describe(\ReflectionProperty|\ReflectionParameter|\ReflectionClass $reflector, ?array &$schema): void
    {
        foreach ($reflector->getAttributes(With::class) as $attribute) {
            $schema = array_replace_recursive($schema ?? [], array_filter((array) $attribute->newInstance(), static fn ($value) => null !== $value));
        }
    }
}
