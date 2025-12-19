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

final class MathReasoning
{
    /**
     * @param Step[] $steps
     */
    public function __construct(
        public array $steps,
        public string $finalAnswer,
        public float $result,
    ) {
    }
}
