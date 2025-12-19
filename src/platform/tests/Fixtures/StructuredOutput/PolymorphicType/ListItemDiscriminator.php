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

#[DiscriminatorMap(
    typeProperty: 'type',
    mapping: [
        'name' => ListItemName::class,
        'age' => ListItemAge::class,
    ]
)]
/**
 * @property string $type
 *
 * With the PHP 8.4^ you can replace the property annotation with a property hook:
 * public string $type {
 *     get;
 * }
 */
interface ListItemDiscriminator
{
}
