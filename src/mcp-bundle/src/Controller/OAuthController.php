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

use Mcp\Exception\ClientRegistrationException;
use Mcp\Server\Transport\Http\Middleware\AuthorizationEndpointMiddleware;
use Mcp\Server\Transport\Http\Middleware\TokenEndpointMiddleware;
use Mcp\Server\Transport\Http\OAuth\AuthorizationServerMetadata;
use Mcp\Server\Transport\Http\OAuth\ClientRegistrarInterface;
use Mcp\Server\Transport\Http\OAuth\ProtectedResourceMetadata;
use Mcp\Server\Transport\Http\OAuth\SigningKeyInterface;
use Symfony\AI\McpBundle\OAuth\NotFoundRequestHandler;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Delivers the native OAuth 2.1 authorization-server endpoints as Symfony
 * controllers, bridging to the SDK middlewares/engine.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class OAuthController
{
    public function __construct(
        private readonly AuthorizationEndpointMiddleware $authorizeMiddleware,
        private readonly TokenEndpointMiddleware $tokenMiddleware,
        private readonly NotFoundRequestHandler $handler,
        private readonly HttpMessageFactoryInterface $psrFactory,
        private readonly HttpFoundationFactoryInterface $httpFoundationFactory,
        private readonly ClientRegistrarInterface $clientRegistrar,
        private readonly AuthorizationServerMetadata $authorizationServerMetadata,
        private readonly ProtectedResourceMetadata $protectedResourceMetadata,
        private readonly SigningKeyInterface $signingKey,
    ) {
    }

    public function authorize(Request $request): Response
    {
        return $this->bridge($request, $this->authorizeMiddleware);
    }

    public function token(Request $request): Response
    {
        return $this->bridge($request, $this->tokenMiddleware);
    }

    public function register(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        if (!\is_array($body)) {
            $body = [];
        }

        try {
            $registration = $this->clientRegistrar->register($body);
        } catch (ClientRegistrationException $exception) {
            return new JsonResponse([
                'error' => $exception->errorCode,
                'error_description' => $exception->getMessage(),
            ], Response::HTTP_BAD_REQUEST, ['Cache-Control' => 'no-store']);
        }

        return new JsonResponse($registration, Response::HTTP_CREATED, ['Cache-Control' => 'no-store']);
    }

    public function authorizationServerMetadata(): JsonResponse
    {
        return new JsonResponse($this->authorizationServerMetadata->jsonSerialize());
    }

    public function protectedResourceMetadata(): JsonResponse
    {
        return new JsonResponse($this->protectedResourceMetadata->jsonSerialize());
    }

    public function jwks(): JsonResponse
    {
        return new JsonResponse(['keys' => [$this->signingKey->getPublicJwk()]]);
    }

    private function bridge(Request $request, AuthorizationEndpointMiddleware|TokenEndpointMiddleware $middleware): Response
    {
        $psrResponse = $middleware->process($this->psrFactory->createRequest($request), $this->handler);

        return $this->httpFoundationFactory->createResponse($psrResponse);
    }
}
