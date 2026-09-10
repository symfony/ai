<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Fixtures\Tool;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final class MappedRecipe
{
    public function __construct(
        #[Assert\Choice(choices: ['flour', 'sugar', 'butter'], message: 'The value must be one of {{ choices }}.')]
        public string $ingredient,
        #[SerializedName('serving_size')]
        public int $servingSize = 1,
        #[Schema(provider: StatusProvider::class)]
        public string $status = 'active',
    ) {
    }
}
