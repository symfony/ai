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

final class CollidingRenameWithProperty
{
    public function __construct(
        #[Schema(name: 'label')]
        public readonly string $title,
        public readonly string $label,
    ) {
    }
}
