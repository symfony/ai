<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Fixtures\StructuredOutput;

final class Step
{
    public function __construct(
        public string $explanation,
        public string $output,
    ) {
    }
}
