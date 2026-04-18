<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Fixtures\StructuredOutput;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

class ExampleDto
{
    public function __construct(
        public string $name,
        #[Schema(enum: [7, 19])] public int $taxRate,
        #[Schema(enum: ['Foo', 'Bar', null])] public ?string $category,
        #[Schema(description: 'The quantity of the ingredient', example: '2 cups')] public ?string $quantity = null,
    ) {
    }
}
