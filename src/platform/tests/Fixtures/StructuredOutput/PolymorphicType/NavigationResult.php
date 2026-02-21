<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\PolymorphicType;

/**
 * Example DTO that uses a single polymorphic interface parameter.
 * Real life example: a navigation tool that accepts different filter types.
 */
final class NavigationResult
{
    public function __construct(
        public Filterable $filter,
    ) {
    }
}
