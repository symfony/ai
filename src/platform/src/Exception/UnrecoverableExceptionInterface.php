<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Exception;

/**
 * Marks failures that are not expected to succeed without changing the request or configuration.
 *
 * @author Dezső Biczó <mxr576@gmail.com>
 */
interface UnrecoverableExceptionInterface extends ExceptionInterface
{
}
