<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\Functional\App\Tool;

use Mcp\Capability\Attribute\McpTool;

/**
 * A public tool with no scope requirements.
 */
final class PublicTool
{
    #[McpTool(name: 'public-tool', description: 'A public tool accessible to all authenticated users')]
    public function execute(): string
    {
        return 'public-result';
    }
}
