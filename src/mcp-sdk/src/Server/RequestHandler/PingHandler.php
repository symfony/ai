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
use PhpLlm\McpSdk\Message\Response;

final class PingHandler extends BaseRequestHandler
{
    public function createResponse(Request $message): Response
    {
        return new Response($message->id, []);
    }

    protected function supportedMethod(): string
    {
        return 'ping';
    }
}
