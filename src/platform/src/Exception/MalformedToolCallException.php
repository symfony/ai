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
 * Thrown when a provider returns malformed tool-call arguments.
 *
 * @author Fabien Potencier <fabien@potencier.org>
 */
class MalformedToolCallException extends RuntimeException
{
}
