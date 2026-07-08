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
 * Thrown when a provider truncates a response because it reached the maximum
 * number of output tokens.
 *
 * Unlike an incomplete stream, the response completed normally at the token
 * ceiling: retrying the identical request would truncate again, so consumers
 * should surface the error and let the user raise the output budget or reduce
 * the request scope instead of retrying.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class MaxOutputTokensException extends RuntimeException
{
}
