<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Mcp\Exception;

/**
 * Exception thrown when MCP server does not respond within the timeout.
 *
 * @author Camille Islasse <guziweb@gmail.com>
 */
class TimeoutException extends ConnectionException
{
}
