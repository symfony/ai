<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Acp\Exception;

use Symfony\AI\Platform\Exception\RuntimeException;

/**
 * Thrown when the ACP binary is not found or not executable.
 */
final class CliNotFoundException extends RuntimeException
{
}
