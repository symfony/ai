<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\DependencyInjection;

final class McpPromptPass extends AbstractMcpPass
{
    protected function getTag(): string
    {
        return 'mcp.prompt';
    }
}
