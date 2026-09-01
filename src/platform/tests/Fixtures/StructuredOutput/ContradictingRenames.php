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

final class ContradictingRenames
{
    #[Schema(name: 'from_property')]
    public string $title;

    public function __construct(
        #[Schema(name: 'from_parameter')]
        string $title,
    ) {
        $this->title = $title;
    }
}
