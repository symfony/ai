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

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

class ListItemName implements ListItemDiscriminator
{
    public function __construct(
        public string $name,
        #[Schema(pattern: '^name$')]
        public string $type = 'name',
    ) {
    }
}
