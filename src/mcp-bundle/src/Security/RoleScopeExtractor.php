<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Extracts scopes from user roles with ROLE_OAUTH2_ prefix.
 *
 * For example:
 * - ROLE_OAUTH2_READ -> "read"
 * - ROLE_OAUTH2_ADMIN -> "admin"
 *
 * This prefix is compatible with league/oauth2-server-bundle.
 * You can create your own extractor by implementing ScopeExtractorInterface.
 */
final class RoleScopeExtractor implements ScopeExtractorInterface
{
    private const PREFIX = 'ROLE_OAUTH2_';

    public function extract(TokenInterface $token): array
    {
        $scopes = [];

        foreach ($token->getRoleNames() as $role) {
            if (str_starts_with($role, self::PREFIX)) {
                $scopes[] = strtolower(substr($role, \strlen(self::PREFIX)));
            }
        }

        return $scopes;
    }
}
