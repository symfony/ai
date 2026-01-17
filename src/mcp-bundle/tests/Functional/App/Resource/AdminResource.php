<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\Functional\App\Resource;

use Mcp\Capability\Attribute\McpResource;
use Symfony\AI\McpBundle\Security\Attribute\RequireScope;

final class AdminResource
{
    /**
     * @return array{uri: string, mimeType: string, text: string}
     */
    #[McpResource(uri: 'admin://secret', name: 'admin-resource', description: 'An admin resource')]
    #[RequireScope('admin')]
    public function execute(): array
    {
        return [
            'uri' => 'admin://secret',
            'mimeType' => 'text/plain',
            'text' => 'Secret admin data',
        ];
    }
}
