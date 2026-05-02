<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Tests\Fixture\JsonSchema;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\SchemaSource;

final class ProductDto
{
    public function __construct(
        #[SchemaSource(CategoryProvider::class)]
        public readonly string $category,
    ) {
    }
}
