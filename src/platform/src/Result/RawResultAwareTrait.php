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

use Symfony\AI\Platform\Result\Exception\RawResultAlreadySetException;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
trait RawResultAwareTrait
{
    protected ?RawResultInterface $rawResult = null;

    public function setRawResult(RawResultInterface $rawResult): void
    {
        if (isset($this->rawResult)) {
            throw new RawResultAlreadySetException();
        }

        $this->rawResult = $rawResult;
    }

    public function getRawResult(): ?RawResultInterface
    {
        return $this->rawResult;
    }
}
