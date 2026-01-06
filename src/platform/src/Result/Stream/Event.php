<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result\Stream;

use Symfony\AI\Platform\Result\StreamResult;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
abstract class Event
{
    public function __construct(
        private readonly StreamResult $result,
        private \Generator $stream,
    ) {
    }

    public function getResult(): StreamResult
    {
        return $this->result;
    }

    public function getStream(): \Generator
    {
        return $this->stream;
    }
}
