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

/**
 * The static enum narrows the cases an earlier describer generated for the property type.
 */
final class NarrowedEnumDto
{
    public function __construct(
        #[Schema(enum: ['low', 'normal'])]
        public readonly string $priority,
    ) {
    }
}
