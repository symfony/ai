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

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\With;

class WithAttributeRefDto
{
    public function __construct(
        #[With(ref: __DIR__.'/../json_schema_ref.json')]
        public ?string $schemaFromFile = null,

        #[With(ref: __DIR__.'/../non_existent.json')]
        public ?string $nonExistentSchema = null,

        #[With(ref: __FILE__)]
        public ?string $nonJsonSchema = null,
    ) {
    }
}
