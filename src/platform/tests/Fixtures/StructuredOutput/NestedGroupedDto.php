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

use Symfony\Component\Serializer\Attribute\Groups;

final class NestedGroupedDto
{
    #[Groups(['write'])]
    public string $title = '';

    #[Groups(['write'])]
    public GroupedDto $child;
}
