<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Fixtures\Tool;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

#[AsTool('tool_with_scalar_constraints', 'A tool with #[Schema] constraints on scalar parameters')]
final class ToolWithScalarConstraints
{
    /**
     * @param string     $reference The order reference, e.g. "ORD-2026-0042"
     * @param int        $quantity  The number of items, between 1 and 10
     * @param array<int> $ratings   Individual ratings, at most 3, without duplicates
     */
    public function __invoke(
        #[Schema(pattern: '^ORD-\d{4}-\d{4}$')]
        string $reference,
        #[Schema(minimum: 1, maximum: 10)]
        int $quantity,
        #[Schema(maxItems: 3, uniqueItems: true)]
        array $ratings,
    ): string {
        return \sprintf('Order "%s": %d item(s).', $reference, $quantity);
    }
}
