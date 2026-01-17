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

use Symfony\AI\McpBundle\Security\Exception\InsufficientScopeException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Checks OAuth scopes for MCP requests.
 */
interface ScopeCheckerInterface
{
    /**
     * Check if the current user has required scopes for the request.
     *
     * @return InsufficientScopeException|null Returns exception if scopes are insufficient, null if OK
     */
    public function check(Request $request): ?InsufficientScopeException;
}
