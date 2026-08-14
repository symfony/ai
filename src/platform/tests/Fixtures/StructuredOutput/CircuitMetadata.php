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

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

final class CircuitMetadata
{
    public function __construct(
        #[Schema(
            description: 'Rest between rounds in seconds',
            minimum: 0,
            name: 'rest_between_rounds',
        )]
        public readonly int $restBetweenRounds,
        public readonly int $rounds,
    ) {
    }
}
