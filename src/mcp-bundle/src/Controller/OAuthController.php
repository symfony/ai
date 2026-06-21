<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Controller;

use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\Client;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\RedirectUri;
use League\Bundle\OAuth2ServerBundle\ValueObject\Scope;
use Symfony\AI\McpBundle\Exception\RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the MCP-specific OAuth endpoints that league/oauth2-server-bundle does
 * not provide: RFC 7591 dynamic client registration, RFC 8414 authorization
 * server metadata, RFC 9728 protected resource metadata, and the JWKS document.
 *
 * The /authorize and /token endpoints, token issuance, PKCE and persistence are
 * all owned by league; this controller never mints or validates tokens.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class OAuthController
{
    /**
     * @param list<string>                            $scopes
     * @param array{authorize: string, token: string, register: string} $endpoints absolute URLs
     */
    public function __construct(
        private readonly ClientManagerInterface $clientManager,
        private readonly string $issuer,
        private readonly string $resource,
        private readonly array $scopes,
        private readonly string $publicKey,
        private readonly ?string $keyId,
        private readonly array $endpoints,
        private readonly bool $clientRegistrationEnabled,
    ) {
    }

    /**
     * RFC 7591 dynamic client registration: persists a new league client.
     */
    public function register(Request $request): JsonResponse
    {
        if (!$this->clientRegistrationEnabled) {
            return $this->error('invalid_request', 'Dynamic client registration is disabled.', Response::HTTP_NOT_FOUND);
        }

        $body = json_decode($request->getContent(), true);
        if (!\is_array($body)) {
            $body = [];
        }

        $redirectUris = $body['redirect_uris'] ?? null;
        if (!\is_array($redirectUris) || [] === $redirectUris) {
            return $this->error('invalid_redirect_uri', 'The "redirect_uris" field is required.');
        }
        foreach ($redirectUris as $uri) {
            if (!\is_string($uri) || !$this->isValidRedirectUri($uri)) {
                return $this->error('invalid_redirect_uri', \sprintf('Invalid redirect URI "%s".', \is_string($uri) ? $uri : ''));
            }
        }

        $isPublic = 'none' === ($body['token_endpoint_auth_method'] ?? 'client_secret_basic');
        $scopes = $this->resolveScopes($body['scope'] ?? null);

        $identifier = bin2hex(random_bytes(16));
        $secret = $isPublic ? null : bin2hex(random_bytes(32));
        $name = \is_string($body['client_name'] ?? null) && '' !== $body['client_name'] ? $body['client_name'] : $identifier;

        $client = new Client($name, $identifier, $secret);
        $client->setActive(true);
        $client->setRedirectUris(...array_map(static fn (string $uri): RedirectUri => new RedirectUri($uri), array_values($redirectUris)));
        $client->setGrants(new Grant('authorization_code'), new Grant('refresh_token'));
        $client->setScopes(...array_map(static fn (string $scope): Scope => new Scope($scope), $scopes));

        $this->clientManager->save($client);

        $response = [
            'client_id' => $identifier,
            'client_id_issued_at' => time(),
            'client_name' => $name,
            'redirect_uris' => array_values($redirectUris),
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => $isPublic ? 'none' : 'client_secret_basic',
            'scope' => implode(' ', $scopes),
        ];
        if (null !== $secret) {
            $response['client_secret'] = $secret;
            $response['client_secret_expires_at'] = 0;
        }

        return new JsonResponse($response, Response::HTTP_CREATED, ['Cache-Control' => 'no-store', 'Pragma' => 'no-cache']);
    }

    /**
     * RFC 8414 authorization server metadata.
     */
    public function authorizationServerMetadata(): JsonResponse
    {
        $data = [
            'issuer' => $this->issuer,
            'authorization_endpoint' => $this->endpoints['authorize'],
            'token_endpoint' => $this->endpoints['token'],
            'jwks_uri' => $this->issuer.'/.well-known/jwks.json',
            'scopes_supported' => $this->scopes,
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post', 'none'],
        ];
        if ($this->clientRegistrationEnabled) {
            $data['registration_endpoint'] = $this->endpoints['register'];
        }

        return new JsonResponse($data);
    }

    /**
     * RFC 9728 protected resource metadata.
     */
    public function protectedResourceMetadata(): JsonResponse
    {
        return new JsonResponse([
            'resource' => $this->resource,
            'authorization_servers' => [$this->issuer],
            'scopes_supported' => $this->scopes,
            'bearer_methods_supported' => ['header'],
        ]);
    }

    /**
     * Publishes the signing public key as a JWKS document (RFC 7517).
     */
    public function jwks(): JsonResponse
    {
        return new JsonResponse(['keys' => [$this->buildJwk()]]);
    }

    /**
     * @return array<string, string>
     */
    private function buildJwk(): array
    {
        $resource = openssl_pkey_get_public($this->readKey($this->publicKey));
        if (false === $resource) {
            throw new RuntimeException('Unable to read the OAuth public key.');
        }

        $details = openssl_pkey_get_details($resource);
        if (false === $details || !isset($details['rsa']['n'], $details['rsa']['e'])) {
            throw new RuntimeException('The OAuth public key is not an RSA key.');
        }

        $n = $this->base64UrlEncode($details['rsa']['n']);
        $e = $this->base64UrlEncode($details['rsa']['e']);

        return [
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => $this->keyId ?? substr(hash('sha256', $n), 0, 16),
            'n' => $n,
            'e' => $e,
        ];
    }

    private function readKey(string $key): string
    {
        if (str_starts_with($key, '-----BEGIN')) {
            return $key;
        }

        $path = str_starts_with($key, 'file://') ? substr($key, 7) : $key;
        $contents = @file_get_contents($path);
        if (false === $contents) {
            throw new RuntimeException(\sprintf('Unable to read the OAuth public key at "%s".', $key));
        }

        return $contents;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * @param mixed $scope
     *
     * @return list<string>
     */
    private function resolveScopes($scope): array
    {
        if (!\is_string($scope) || '' === trim($scope)) {
            return $this->scopes;
        }

        $requested = array_values(array_filter(explode(' ', $scope), static fn (string $value): bool => '' !== $value));
        $granted = array_values(array_intersect($requested, $this->scopes));

        return [] === $granted ? $this->scopes : $granted;
    }

    private function isValidRedirectUri(string $uri): bool
    {
        $parts = parse_url($uri);
        if (false === $parts || !isset($parts['scheme'])) {
            return false;
        }

        $scheme = strtolower($parts['scheme']);
        if ('https' === $scheme) {
            return true;
        }

        // Loopback redirect for native apps (RFC 8252).
        if ('http' === $scheme && \in_array($parts['host'] ?? '', ['localhost', '127.0.0.1', '[::1]'], true)) {
            return true;
        }

        // Custom (private-use) scheme for native apps.
        return 'http' !== $scheme && str_contains($uri, ':');
    }

    private function error(string $error, string $description, int $status = Response::HTTP_BAD_REQUEST): JsonResponse
    {
        return new JsonResponse([
            'error' => $error,
            'error_description' => $description,
        ], $status, ['Cache-Control' => 'no-store']);
    }
}
