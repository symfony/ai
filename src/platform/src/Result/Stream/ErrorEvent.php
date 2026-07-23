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
 * @author Ghislain Flandin <ghislain.flandin@gmail.com>
 */
final class ErrorEvent extends Event
{
    public function __construct(StreamResult $result, private readonly \Throwable $error)
    {
        parent::__construct($result);
    }

    public function getError(): \Throwable
    {
        return $this->error;
    }
}
