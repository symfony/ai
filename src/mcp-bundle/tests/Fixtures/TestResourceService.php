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

use Mcp\Capability\Attribute\McpResource;

#[McpResource(uri: 'test://resource', name: 'test_resource', description: 'A test resource')]
class TestResourceService
{
    public function __invoke(): string
    {
        return 'resource content';
    }
}
