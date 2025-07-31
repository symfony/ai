<?php

namespace Symfony\AI\McpBundle\Session;

use Symfony\AI\McpSdk\Exception\InvalidSessionIdException;
use Symfony\AI\McpSdk\Server\Transport\StreamableHttp\SessionIdentifier;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

readonly class SessionIdentifierResolver implements ValueResolverInterface
{
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if ($argument->getType() !== SessionIdentifier::class) {
            return [];
        }

        if (!$request->attributes->has('_mcp_session_id')) {
            return match($argument->isNullable()) {
                true => [null],
                false => []
            };
        }

        $sessionIdentifier = $request->attributes->get('_mcp_session_id');
        if (!$sessionIdentifier instanceof SessionIdentifier) {
            throw new InvalidSessionIdException(sprintf('Session "%s" not found.', $sessionIdentifier));
        }

        return [$sessionIdentifier];
    }
}
