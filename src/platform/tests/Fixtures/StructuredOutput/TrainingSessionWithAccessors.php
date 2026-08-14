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

final class TrainingSessionWithAccessors
{
    private int $restBetweenRounds;

    public function getRestBetweenRounds(): int
    {
        return $this->restBetweenRounds;
    }

    public function setRestBetweenRounds(
        #[Schema(name: 'rest_between_rounds')]
        int $restBetweenRounds,
    ): void {
        $this->restBetweenRounds = $restBetweenRounds;
    }
}
