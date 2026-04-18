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

class SchemaAttributeValuesDto
{
    /**
     * @param string $name this is the PHPDoc description
     */
    public function __construct(
        #[Schema(description: 'This is the attribute description.', example: 'Attribute example')]
        public string $name,
    ) {
    }
}
