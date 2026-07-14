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

final class GroupedDto
{
    #[Groups(['read', 'write'])]
    public string $name = '';

    #[Groups(['write'])]
    public ?int $age = null;

    #[Groups(['read'])]
    public string $slug = '';

    public string $internal = '';
}
