<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Result\Stream\Delta\DeltaInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ChoiceResult extends BaseResult implements DeltaInterface
{
    /**
     * @param ResultInterface[] $results
     */
    public function __construct(
        private readonly array $results,
    ) {
        if (1 >= \count($results)) {
            throw new InvalidArgumentException('A choice result must contain at least two results.');
        }
    }

    /**
     * @return ResultInterface[]
     */
    public function getContent(): array
    {
        return $this->results;
    }
}
