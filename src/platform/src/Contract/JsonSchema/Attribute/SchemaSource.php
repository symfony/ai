<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\JsonSchema\Attribute;

/**
 * Attaches a runtime-computed JSON Schema fragment to a parameter or property.
 *
 * @author Camille Islasse <guiziweb@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
final class SchemaSource
{
    /**
     * @param string               $provider Service ID of a SchemaProviderInterface implementation (FQCN or any container ID)
     * @param array<string, mixed> $context
     */
    public function __construct(
        public readonly string $provider,
        public readonly array $context = [],
    ) {
    }
}
