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

final class GroupedPage
{
    public function __construct(
        #[Groups(['ai'])]
        public ?string $title = null,
        public ?string $internalNote = null,
        #[Groups(['ai'])]
        public ?GroupedAuthor $author = null,
    ) {
    }
}
