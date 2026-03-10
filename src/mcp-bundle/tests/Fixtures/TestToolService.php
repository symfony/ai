<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\Fixtures;

use Mcp\Capability\Attribute\McpTool;

#[McpTool(name: 'test_tool', description: 'A test tool')]
class TestToolService
{
    public function __invoke(string $input): string
    {
        return $input;
    }
}
