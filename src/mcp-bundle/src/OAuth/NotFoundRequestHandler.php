<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\OAuth;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Inner handler for the OAuth endpoint middlewares. The middlewares always
 * short-circuit on their configured path, so this is only a fallback.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class NotFoundRequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->responseFactory->createResponse(404);
    }
}
