<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\UnionType;

final class UnionTypeDto
{
    public function __construct(
        public UnixTimestampUnion|HumanReadableTimeUnion|null $time,
    ) {
    }
}
