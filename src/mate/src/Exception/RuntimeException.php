<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Exception;

/**
 * Base exception class for runtime errors in the Mate component.
 *
 * @internal
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class RuntimeException extends \RuntimeException implements ExceptionInterface
{
}
