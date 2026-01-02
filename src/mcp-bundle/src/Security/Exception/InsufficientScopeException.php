<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Security\Exception;

/**
 * Exception thrown when a user lacks the required OAuth scopes.
 *
 * This exception is converted to an HTTP 403 Forbidden response
 * with a WWW-Authenticate header per RFC 6750 Section 3.1.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc6750#section-3.1
 */
final class InsufficientScopeException extends \RuntimeException
{
    /**
     * @param list<string> $requiredScopes The scopes that are required but missing
     */
    public function __construct(
        private readonly array $requiredScopes,
        string $message = '',
    ) {
        if ('' === $message) {
            $message = \sprintf('Insufficient scope. Required: %s', implode(' ', $this->requiredScopes));
        }

        parent::__construct($message);
    }

    /**
     * @return list<string>
     */
    public function getRequiredScopes(): array
    {
        return $this->requiredScopes;
    }

    /**
     * Returns the scopes as a space-separated string for the WWW-Authenticate header.
     */
    public function getScopeString(): string
    {
        return implode(' ', $this->requiredScopes);
    }
}
