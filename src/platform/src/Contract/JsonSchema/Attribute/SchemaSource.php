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

use Symfony\AI\Platform\Contract\JsonSchema\Provider\SchemaProviderInterface;
use Symfony\AI\Platform\Exception\InvalidArgumentException;

/**
 * Attaches a runtime-computed JSON Schema fragment to a parameter or property.
 *
 * @author Camille Islasse <guiziweb@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
final class SchemaSource
{
    /**
     * @param class-string<SchemaProviderInterface> $provider
     * @param array<string, mixed>                  $context
     */
    public function __construct(
        public readonly string $provider,
        public readonly array $context = [],
    ) {
        if (!is_subclass_of($provider, SchemaProviderInterface::class)) {
            throw new InvalidArgumentException(\sprintf('The provider "%s" must implement "%s".', $provider, SchemaProviderInterface::class));
        }
    }
}
