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

use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Serializer\Attribute\SerializedName;

final class MathReasoningWithAttributes
{
    /**
     * @param Step[] $steps
     */
    public function __construct(
        public array $steps,
        #[SerializedName('foo')]
        public string $finalAnswer,
        #[Ignore]
        public float $result,
    ) {
    }
}
