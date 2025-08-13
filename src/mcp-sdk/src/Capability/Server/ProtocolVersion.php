<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpSdk\Capability\Server;

enum ProtocolVersion: string
{
    case V2024_11_05 = '2024-11-05';
    case V2025_03_26 = '2025-03-26';
    case V2025_06_18 = '2025-06-18';
}
