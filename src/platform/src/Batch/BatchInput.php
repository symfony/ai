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

use Symfony\AI\Platform\Message\MessageBag;

/**
 * Represents a single request within a batch submission.
 *
 * @author Camille Islasse <cams.development@gmail.com>
 */
final class BatchInput
{
    public function __construct(
        private readonly string $id,
        private readonly MessageBag $input,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getInput(): MessageBag
    {
        return $this->input;
    }
}
