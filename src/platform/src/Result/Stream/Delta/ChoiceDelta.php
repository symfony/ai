<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result\Stream\Delta;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class ChoiceDelta implements DeltaInterface
{
    /**
     * @param DeltaInterface[] $deltas
     */
    public function __construct(
        private readonly array $deltas,
    ) {
    }

    /**
     * @return DeltaInterface[]
     */
    public function getDeltas(): array
    {
        return $this->deltas;
    }
}
