<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Batch;

use Symfony\AI\Platform\Result\BaseResult;

/**
 * Result wrapping the {@see BatchJob} returned when a batch is submitted.
 *
 * @author Camille Islasse <cams.development@gmail.com>
 */
final class BatchJobResult extends BaseResult
{
    public function __construct(
        private readonly BatchJob $job,
    ) {
    }

    public function getContent(): BatchJob
    {
        return $this->job;
    }
}
