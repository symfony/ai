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
 * Thrown when a provider stream ends before its terminal event.
 *
 * An incomplete stream usually indicates a transient network failure, so
 * consumers may want to retry the request.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class IncompleteStreamException extends RuntimeException
{
}
