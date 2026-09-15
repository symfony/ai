<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Server;

use Mcp\Schema\Tool;

/**
 * Decides which tools are advertised to the current client.
 *
 * This controls discovery through tools/list only. Implementations must enforce authorization
 * separately when a tool is called.
 *
 * @author Ousama Ben Younes <2910651+ousamabenyounes@users.noreply.github.com>
 */
interface ToolListFilterInterface
{
    public function isVisible(Tool $tool): bool;
}
