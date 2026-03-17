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

use Mcp\Capability\Attribute\McpResourceTemplate;

#[McpResourceTemplate(uriTemplate: 'test://resource/{id}', name: 'test_template', description: 'A test resource template')]
class TestResourceTemplateService
{
    public function __invoke(string $id): string
    {
        return "resource $id";
    }
}
