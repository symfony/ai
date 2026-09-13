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

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

final class ToolExclusiveMaximum
{
    public function __invoke(
        #[Schema(exclusiveMaximum: 10)]
        int $value,
    ): string {
        return "Value: $value";
    }
}
