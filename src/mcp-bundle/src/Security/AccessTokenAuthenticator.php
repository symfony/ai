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

use Mcp\Server\Transport\Http\OAuth\AuthorizationResult;
use Mcp\Server\Transport\Http\OAuth\AuthorizationTokenValidatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Firewall authenticator validating MCP OAuth bearer tokens via the SDK token
 * validator and loading the user from the token's subject claim.
 *
 * Register it as a custom_authenticator on the firewall protecting the MCP
 * endpoint. On failure it returns null so the firewall entry point can render
 * the RFC 9728 challenge.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class AccessTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly AuthorizationTokenValidatorInterface $tokenValidator,
        private readonly string $mcpPath,
    ) {
    }

    public function supports(Request $request): bool
    {
        if (!str_starts_with($request->getPathInfo(), $this->mcpPath)) {
            return false;
        }

        return str_starts_with((string) $request->headers->get('Authorization', ''), 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $accessToken = trim(substr((string) $request->headers->get('Authorization', ''), 7));
        if ('' === $accessToken) {
            throw new CustomUserMessageAuthenticationException('Missing bearer token.');
        }

        $result = $this->tokenValidator->validate($accessToken);
        if (!$result->isAllowed()) {
            throw new CustomUserMessageAuthenticationException($result->getErrorDescription() ?? 'Invalid access token.');
        }

        $subject = $this->subject($result);
        if (null === $subject) {
            throw new CustomUserMessageAuthenticationException('Access token is missing the subject claim.');
        }

        foreach ($result->getAttributes() as $name => $value) {
            $request->attributes->set($name, $value);
        }

        return new SelfValidatingPassport(new UserBadge($subject));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return null;
    }

    private function subject(AuthorizationResult $result): ?string
    {
        $subject = $result->getAttributes()['oauth.subject'] ?? null;

        return \is_string($subject) && '' !== $subject ? $subject : null;
    }
}
