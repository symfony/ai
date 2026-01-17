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
use Symfony\AI\McpBundle\Security\Attribute\RequireScope;

/**
 * An admin tool that requires the 'admin' scope.
 */
final class AdminTool
{
    #[McpTool(name: 'admin-tool', description: 'An admin tool requiring admin scope')]
    #[RequireScope('admin')]
    public function execute(): string
    {
        return 'admin-result';
    }
}
