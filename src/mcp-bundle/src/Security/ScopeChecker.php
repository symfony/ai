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
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Checks OAuth scopes for MCP JSON-RPC requests.
 */
final class ScopeChecker implements ScopeCheckerInterface
{
    /**
     * @param array<string, list<string>> $toolScopes     Map of tool-name => required scopes
     * @param array<string, list<string>> $promptScopes   Map of prompt-name => required scopes
     * @param array<string, list<string>> $resourceScopes Map of resource-uri => required scopes
     */
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly ScopeExtractorInterface $scopeExtractor,
        private readonly array $toolScopes = [],
        private readonly array $promptScopes = [],
        private readonly array $resourceScopes = [],
    ) {
    }

    public function check(Request $request): ?InsufficientScopeException
    {
        if ('POST' !== $request->getMethod()) {
            return null;
        }

        try {
            $payload = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null; // Let the SDK handle malformed JSON
        }

        // Handle batch requests
        $messages = isset($payload['jsonrpc']) ? [$payload] : $payload;

        foreach ($messages as $message) {
            if (!isset($message['method'])) {
                continue;
            }

            $requiredScopes = $this->getRequiredScopes($message);

            if ([] === $requiredScopes) {
                continue;
            }

            $violation = $this->checkScopes($requiredScopes);

            if (null !== $violation) {
                return $violation;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $message
     *
     * @return list<string>
     */
    private function getRequiredScopes(array $message): array
    {
        $method = $message['method'];
        $params = $message['params'] ?? [];

        return match ($method) {
            'tools/call' => $this->toolScopes[$params['name'] ?? ''] ?? [],
            'prompts/get' => $this->promptScopes[$params['name'] ?? ''] ?? [],
            'resources/read' => $this->resourceScopes[$params['uri'] ?? ''] ?? [],
            default => [],
        };
    }

    /**
     * @param list<string> $requiredScopes
     */
    private function checkScopes(array $requiredScopes): ?InsufficientScopeException
    {
        $token = $this->tokenStorage->getToken();

        if (null === $token) {
            return null;
        }

        $userScopes = $this->scopeExtractor->extract($token);
        $missingScopes = array_diff($requiredScopes, $userScopes);

        if ([] !== $missingScopes) {
            return new InsufficientScopeException(array_values($missingScopes));
        }

        return null;
    }
}
