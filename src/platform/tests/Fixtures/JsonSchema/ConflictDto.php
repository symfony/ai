<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Fixtures\JsonSchema;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\SchemaSource;

final class ConflictDto
{
    public function __construct(
        #[Schema(enum: ['a', 'b'])]
        #[SchemaSource(StatusProvider::class)]
        public readonly string $status,
    ) {
    }
}
