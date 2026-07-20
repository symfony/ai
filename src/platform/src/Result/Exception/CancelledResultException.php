<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result\Exception;

use Symfony\AI\Platform\Exception\RuntimeException;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class CancelledResultException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The result has been cancelled and cannot be consumed anymore.');
    }
}
