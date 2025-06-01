<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Response\Exception;

use Symfony\AI\Platform\Exception\RuntimeException;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
final class RawResponseAlreadySetException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The raw response was already set.');
    }
}
