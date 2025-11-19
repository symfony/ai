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

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\With;

final class MathReasoning
{
    /**
     * @param Step[] $steps
     */
    public function __construct(
        public array $steps,
        #[With(minimum: 0, maximum: 100)]
        public int $confidence,
        public string $finalAnswer,
    ) {
    }
}
