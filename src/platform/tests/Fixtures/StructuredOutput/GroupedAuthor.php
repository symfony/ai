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

final class GroupedAuthor
{
    public function __construct(
        #[Groups(['ai'])]
        public ?string $name = null,
        public ?string $email = null,
    ) {
    }
}
