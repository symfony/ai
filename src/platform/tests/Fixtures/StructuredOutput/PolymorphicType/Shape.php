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

use Symfony\Component\Serializer\Attribute\DiscriminatorMap;

/**
 * The discriminator "kind" is not a property of the mapped classes, the serializer handles it on its own.
 */
#[DiscriminatorMap(
    typeProperty: 'kind',
    mapping: [
        'circle' => Circle::class,
        'square' => Square::class,
    ]
)]
interface Shape
{
}
