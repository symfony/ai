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

use Symfony\Component\Validator\Constraints as Assert;

final class UserWithGroupedConstraints
{
    #[Assert\Positive]
    public int $id = 0;
    #[Assert\NotBlank(groups: ['strict'])]
    public string $name = '';
}
