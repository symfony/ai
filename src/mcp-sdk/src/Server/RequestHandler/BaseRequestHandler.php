<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpLlm\McpSdk\Server\RequestHandler;

use PhpLlm\McpSdk\Message\Request;
use PhpLlm\McpSdk\Server\RequestHandlerInterface;

abstract class BaseRequestHandler implements RequestHandlerInterface
{
    public function supports(Request $message): bool
    {
        return $message->method === $this->supportedMethod();
    }

    abstract protected function supportedMethod(): string;
}
