<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Security\Attribute;

/**
 * Requires specific OAuth scopes to access an MCP capability.
 *
 * Can be used on a class (applies to all methods) or on individual methods.
 *
 * @example
 *     #[McpTool(name: 'delete-user')]
 *     #[RequireScope('admin')]
 *     public function deleteUser(int $userId): string
 * @example
 *     #[RequireScope(['read', 'write'])]
 *     public function updateData(): string
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class RequireScope
{
    /** @var list<string> */
    public readonly array $scopes;

    /**
     * @param string|list<string> $scopes One or more required scopes (all must be present)
     */
    public function __construct(string|array $scopes)
    {
        $this->scopes = \is_array($scopes) ? $scopes : [$scopes];
    }
}
