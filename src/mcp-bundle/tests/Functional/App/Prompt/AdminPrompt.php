<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\Functional\App\Prompt;

use Mcp\Capability\Attribute\McpPrompt;
use Symfony\AI\McpBundle\Security\Attribute\RequireScope;

final class AdminPrompt
{
    /**
     * @return list<array{role: string, content: string}>
     */
    #[McpPrompt(name: 'admin-prompt', description: 'An admin prompt')]
    #[RequireScope('admin')]
    public function execute(): array
    {
        return [
            ['role' => 'user', 'content' => 'Admin prompt content'],
        ];
    }
}
