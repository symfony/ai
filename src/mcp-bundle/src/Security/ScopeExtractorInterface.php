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
 * Extracts OAuth scopes from a security token.
 *
 * Implement this interface to define how scopes are extracted from your tokens.
 */
interface ScopeExtractorInterface
{
    /**
     * @return list<string> The scopes granted to the current token
     */
    public function extract(TokenInterface $token): array;
}
