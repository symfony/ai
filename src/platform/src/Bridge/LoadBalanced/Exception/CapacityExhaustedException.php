<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\LoadBalanced\Exception;

use Symfony\AI\Platform\Exception\RuntimeException;

/**
 * Thrown when no platform has available capacity to handle a request.
 *
 * @author Paul Clegg <hello@clegginabox.co.uk>
 */
class CapacityExhaustedException extends RuntimeException
{
}
