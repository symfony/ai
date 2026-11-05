<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\PlatformBridge\Exception;

/**
 * Thrown when config/platform_bridges.yaml is missing or invalid.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class PlatformBridgeCurationException extends \RuntimeException
{
}
