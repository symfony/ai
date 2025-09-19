<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\MCP\Tools;

use Mcp\Capability\Attribute\McpTool;

/**
 * @author Tom Hart <tom.hart.221@gmail.com>
 */
class CurrentTimeTool
{
    /**
     * Returns the current time in UTC.
     *
     * @param string $format The format of the time, e.g. "Y-m-d H:i:s"
     */
    #[McpTool(name: 'current-time')]
    public function getCurrentTime(string $format = 'Y-m-d H:i:s'): string
    {
        return (new \DateTime('now', new \DateTimeZone('UTC')))->format($format);
    }
}
