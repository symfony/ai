<?php

namespace Symfony\AI\McpBundle\Session;

use Symfony\AI\McpSdk\Exception\InvalidSessionIdException;
use Symfony\AI\McpSdk\Server\Transport\StreamableHttp\Session\Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

readonly class SessionResolver implements ValueResolverInterface
{
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if ($argument->getType() !== Session::class) {
            return [];
        }

        if (!$request->attributes->has('_mcp_session')) {
            return match($argument->isNullable()) {
                true => [null],
                false => throw new InvalidSessionIdException('Session not found.')
            };
        }

        $session = $request->attributes->get('_mcp_session');
        if (!$session instanceof Session) {
            throw new InvalidSessionIdException('Session not found.');
        }

        return [$session];
    }
}
